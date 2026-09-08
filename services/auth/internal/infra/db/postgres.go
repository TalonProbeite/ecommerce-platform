// Package db provides database initialization and migration utilities.
package db

import (
	"context"
	"errors"
	"fmt"
	"shop/auth/migrations"
	"time"

	"github.com/golang-migrate/migrate/v4"
	_ "github.com/golang-migrate/migrate/v4/database/postgres" // PostgreSQL driver for migrate
	"github.com/golang-migrate/migrate/v4/source/iofs"
	_ "github.com/jackc/pgx/v5/stdlib" // pgx stdlib driver for sqlx
	"github.com/jmoiron/sqlx"
)

// NewPostgresDB connects to PostgreSQL using the provided DSN and verifies connection.
func NewPostgresDB(dsn string) (*sqlx.DB, error) {
	db, err := sqlx.Open("pgx", dsn)
	if err != nil {
		return nil, fmt.Errorf("failed to open postgres connection: %w", err)
	}

	db.SetMaxOpenConns(25)
	db.SetMaxIdleConns(5)
	db.SetConnMaxLifetime(15 * time.Minute)

	ctx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
	defer cancel()

	if err := db.PingContext(ctx); err != nil {
		if closeErr := db.Close(); closeErr != nil {
			return nil, fmt.Errorf("failed to ping postgres (%w) and failed to close db (%w)", err, closeErr)
		}
		return nil, fmt.Errorf("failed to ping postgres: %w", err)
	}

	return db, nil
}

// RunMigrations executes embedded SQL migrations against the target database DSN.
func RunMigrations(dsn string) (err error) {
	driver, err := iofs.New(migrations.FS, ".")
	if err != nil {
		return fmt.Errorf("failed to create migration driver: %w", err)
	}

	m, err := migrate.NewWithSourceInstance("iofs", driver, dsn)
	if err != nil {
		return fmt.Errorf("failed to create migrate instance: %w", err)
	}
	defer func() {
		srcErr, dbErr := m.Close()
		if srcErr != nil {
			err = errors.Join(err, fmt.Errorf("migration source error: %w", srcErr))
		}
		if dbErr != nil {
			err = errors.Join(err, fmt.Errorf("migration database error: %w", dbErr))
		}
	}()

	if err := m.Up(); err != nil && !errors.Is(err, migrate.ErrNoChange) {
		return fmt.Errorf("failed to run migrations: %w", err)
	}

	return nil
}
