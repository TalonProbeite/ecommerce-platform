package repository_test

import (
	"context"
	"errors"
	_ "github.com/jackc/pgx/v5/stdlib"
	"github.com/jmoiron/sqlx"
	"github.com/redis/go-redis/v9"
	"os"
	"shop/auth/internal/domain"
	"shop/auth/internal/infra/repository"
	"shop/auth/migrations"
	"shop/shared/roles"
	"strings"
	"sync"
	"testing"
	"time"
)

func TestOAuthRepositoryRegistration(t *testing.T) {
	dsn := os.Getenv("AUTH_TEST_DATABASE_URL")
	if dsn == "" {
		t.Skip("set AUTH_TEST_DATABASE_URL")
	}
	schema, err := migrations.FS.ReadFile("000001_init.up.sql")
	if err != nil {
		t.Fatal(err)
	}
	for _, driver := range []string{"postgres", "pgx"} {
		t.Run(driver, func(t *testing.T) {
			db, err := sqlx.Connect(driver, dsn)
			if err != nil {
				t.Fatal(err)
			}
			t.Cleanup(func() {
				if err := db.Close(); err != nil {
					t.Error(err)
				}
			})
			db.SetMaxOpenConns(1)
			db.MustExec(strings.ReplaceAll(string(schema), "CREATE TABLE", "CREATE TEMP TABLE"))
			repo := repository.NewUserRepo(db)
			user := &domain.User{Email: "google@gmail.com", FirstName: "Jane", LastName: "Doe", Phone: "+48123456789", Provider: domain.ProviderGoogle, ProviderUserID: "google-sub", IsActive: true, IsEmailVerified: true}
			id, err := repo.CreateWithOauth(context.Background(), user)
			if err != nil {
				t.Fatal(err)
			}
			found, err := repo.GetByOAuth(context.Background(), "google", "google-sub")
			if err != nil {
				t.Fatal(err)
			}
			if found.ID != id.String() || !found.IsActive || found.Role != roles.RoleCustomer || found.Password != "" {
				t.Fatalf("OAuth lookup: %+v", found)
			}
			p, err := repo.GetByIDProfile(context.Background(), id.String())
			if err != nil {
				t.Fatal(err)
			}
			if !p.IsOAuth || !p.IsEmailVerified {
				t.Fatalf("profile: %+v", p)
			}
			login, err := repo.GetByEmail(context.Background(), user.Email)
			if err != nil {
				t.Fatal(err)
			}
			if !login.IsOAuth || login.Password != "" {
				t.Fatalf("login lookup: %+v", login)
			}
			_, err = repo.CreateWithOauth(context.Background(), user)
			if !errors.Is(err, domain.ErrEmailAlreadyExists) {
				t.Fatalf("duplicate: %v", err)
			}
			conflicting := *user
			conflicting.Email = "another@gmail.com"
			_, err = repo.CreateWithOauth(context.Background(), &conflicting)
			if !errors.Is(err, domain.ErrEmailAlreadyExists) {
				t.Fatalf("duplicate identity: %v", err)
			}
			var count int
			if err := db.Get(&count, "SELECT COUNT(*) FROM users"); err != nil {
				t.Fatal(err)
			}
			if count != 1 {
				t.Fatalf("failed transaction left %d users", count)
			}
		})
	}
}

func TestOAuthRedisTransactionsAndLeases(t *testing.T) {
	endpoint := os.Getenv("AUTH_TEST_REDIS_URL")
	if endpoint == "" {
		t.Skip("set AUTH_TEST_REDIS_URL")
	}
	opts, err := redis.ParseURL(endpoint)
	if err != nil {
		t.Fatal(err)
	}
	client := redis.NewClient(opts)
	t.Cleanup(func() {
		if err := client.Close(); err != nil {
			t.Error(err)
		}
	})
	repo := repository.NewSessionRepo(client)
	ctx := context.Background()
	prefix := "oauth-test:" + time.Now().Format("150405.000000000")
	if err := repo.SaveEntry(ctx, prefix+":state", "transaction", time.Minute); err != nil {
		t.Fatal(err)
	}
	var wg sync.WaitGroup
	results := make(chan error, 8)
	for range 8 {
		wg.Go(func() { _, err := repo.ConsumeEntry(ctx, prefix+":state"); results <- err })
	}
	wg.Wait()
	close(results)
	successes := 0
	for err := range results {
		if err == nil {
			successes++
		} else if !errors.Is(err, domain.ErrEntryNotFound) {
			t.Fatal(err)
		}
	}
	if successes != 1 {
		t.Fatalf("state consumed %d times", successes)
	}
	lock := prefix + ":lock"
	ok, err := repo.AcquireLock(ctx, lock, "first", 100*time.Millisecond)
	if err != nil || !ok {
		t.Fatalf("first lock: %v %v", ok, err)
	}
	ok, err = repo.AcquireLock(ctx, lock, "second", time.Minute)
	if err != nil || ok {
		t.Fatalf("parallel lock: %v %v", ok, err)
	}
	if err := repo.ReleaseLock(ctx, lock, "second"); err != nil {
		t.Fatal(err)
	}
	if _, err := repo.GetValue(ctx, lock); err != nil {
		t.Fatal("foreign release removed lock")
	}
	time.Sleep(150 * time.Millisecond)
	ok, err = repo.AcquireLock(ctx, lock, "second", time.Minute)
	if err != nil || !ok {
		t.Fatalf("expired lease: %v %v", ok, err)
	}
	if err := repo.ReleaseLock(ctx, lock, "first"); err != nil {
		t.Fatal(err)
	}
	if value, err := repo.GetValue(ctx, lock); err != nil || value != "second" {
		t.Fatalf("old owner removed new lease: %q %v", value, err)
	}
	if err := repo.ReleaseLock(ctx, lock, "second"); err != nil {
		t.Fatal(err)
	}
	if _, err := repo.GetValue(ctx, lock); !errors.Is(err, domain.ErrEntryNotFound) {
		t.Fatalf("released lease: %v", err)
	}
	pending := prefix + ":pending"
	if err := repo.SaveEntry(ctx, pending, "profile", 100*time.Millisecond); err != nil {
		t.Fatal(err)
	}
	for range 2 {
		if _, err := repo.GetValue(ctx, pending); err != nil {
			t.Fatal(err)
		}
	}
	time.Sleep(150 * time.Millisecond)
	if _, err := repo.GetValue(ctx, pending); !errors.Is(err, domain.ErrEntryNotFound) {
		t.Fatalf("expired profile: %v", err)
	}
}
