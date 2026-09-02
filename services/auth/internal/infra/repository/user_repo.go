package repository

import (
	"context"
	"fmt"
	"shop/auth/internal/domain"
	"github.com/google/uuid"

	"github.com/jmoiron/sqlx"
)

type UserRepo struct {
	pg *sqlx.DB
}

func (r UserRepo) GetByEmail(ctx context.Context, email string) (*domain.User, error) {
	var u domain.User

	query := `SELECT id , email , password_hash, role , is_active FROM users WHERE email = $1`

	err := r.pg.GetContext(ctx, &u, query, email)
	if err != nil {
		return nil, err
	}

	return &u, nil
}

func (r *UserRepo) Create(ctx context.Context, u *domain.User) error {
	userID, err := uuid.NewV7()
	if err != nil {
		return fmt.Errorf("generate user uuid v7: %w", err)
	}

	profileID, err := uuid.NewV7()
	if err != nil {
		return fmt.Errorf("generate profile uuid v7: %w", err)
	}

	u.ID = userID.String()

	if u.Role == "" {
		u.Role = "user"
	}

	tx, err := r.pg.BeginTxx(ctx, nil)
	if err != nil {
		return fmt.Errorf("begin tx: %w", err)
	}
	defer tx.Rollback()

	userQuery := `
		INSERT INTO users (id, email, password_hash, role, is_active)
		VALUES ($1, $2, $3, $4, $5)
	`
	_, err = tx.ExecContext(ctx, userQuery, u.ID, u.Email, u.Password, u.Role, u.IsActive)
	if err != nil {
		return fmt.Errorf("insert user: %w", err)
	}

	profileQuery := `
		INSERT INTO profiles (id, user_id, first_name, last_name, phone)
		VALUES ($1, $2, $3, $4, $5)
	`
	_, err = tx.ExecContext(ctx, profileQuery, profileID.String(), u.ID, u.FirstName, u.LasyName, u.Phone)
	if err != nil {
		return fmt.Errorf("insert profile: %w", err)
	}

	if err := tx.Commit(); err != nil {
		return fmt.Errorf("commit tx: %w", err)
	}

	return nil
}
