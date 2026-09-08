// Package repository provides data persistence implementations for the auth service.
package repository

import (
	"context"
	"database/sql"
	"errors"
	"fmt"

	"github.com/google/uuid"
	"github.com/jmoiron/sqlx"

	"shop/auth/internal/domain"
)

// UserRepo handles user database operations in PostgreSQL.
type UserRepo struct {
	pg *sqlx.DB
}

// NewUserRepo constructs a new UserRepo.
func NewUserRepo(pg *sqlx.DB) *UserRepo {
	return &UserRepo{pg: pg}
}

// GetByEmail retrieves a user by email address.
func (r *UserRepo) GetByEmail(ctx context.Context, email string) (*domain.User, error) {
	var u domain.User

	query := `SELECT id, email, password_hash, role, is_active FROM users WHERE email = $1`

	err := r.pg.GetContext(ctx, &u, query, email)
	if err != nil {
		return nil, err
	}

	return &u, nil
}

// Create inserts a new user and user profile in a single transaction.
func (r *UserRepo) Create(ctx context.Context, u *domain.User) (userID uuid.UUID, err error) {
	if u.Role == "" {
		u.Role = domain.RoleCustomer
	}

	tx, err := r.pg.BeginTxx(ctx, nil)
	if err != nil {
		return uuid.Nil, fmt.Errorf("begin tx: %w", err)
	}
	defer func() {
		if rollbackErr := tx.Rollback(); rollbackErr != nil && !errors.Is(rollbackErr, sql.ErrTxDone) {
			err = errors.Join(err, fmt.Errorf("rollback error: %w", rollbackErr))
		}
	}()

	userQuery := `
		INSERT INTO users (email, password_hash, role, is_active, is_email_verified)
		VALUES ($1, $2, $3, $4, $5)
		RETURNING id
	`
	err = tx.QueryRowContext(
		ctx,
		userQuery,
		u.Email,
		u.Password,
		u.Role,
		u.IsActive,
		u.IsEmailVerified,
	).Scan(&userID)

	if err != nil {
		return uuid.Nil, fmt.Errorf("insert user: %w", err)
	}

	u.ID = userID.String()

	profileQuery := `
		INSERT INTO profiles (user_id, first_name, last_name, phone)
		VALUES ($1, $2, $3, $4)
	`
	_, err = tx.ExecContext(
		ctx,
		profileQuery,
		u.ID,
		u.FirstName,
		u.LastName,
		u.Phone,
	)

	if err != nil {
		return uuid.Nil, fmt.Errorf("insert profile: %w", err)
	}

	if err := tx.Commit(); err != nil {
		return uuid.Nil, fmt.Errorf("commit tx: %w", err)
	}

	return userID, nil
}

// GetStatus retrieves the user's active status.
func (r *UserRepo) GetStatus(ctx context.Context, userID string) (bool, error) {
	query := `SELECT is_active FROM users WHERE id = $1`
	var isActive bool

	if err := r.pg.GetContext(ctx, &isActive, query, userID); err != nil {
		return false, fmt.Errorf("get status by id: %w", err)
	}

	return isActive, nil
}

// SetVerified marks a user's email address as verified.
func (r *UserRepo) SetVerified(ctx context.Context, userID string) error {
	query := `UPDATE users SET is_email_verified=true WHERE id = $1`
	res, err := r.pg.ExecContext(ctx, query, userID)
	if err != nil {
		return fmt.Errorf("failed to update email confirmation field: %w", err)
	}
	rows, err := res.RowsAffected()
	if err != nil {
		return fmt.Errorf("get rows affected: %w", err)
	}
	if rows == 0 {
		return fmt.Errorf("user not found")
	}

	return nil
}

// GetByID retrieves a user by ID.
func (r *UserRepo) GetByID(ctx context.Context, userID string) (*domain.User, error) {
	var u domain.User

	query := `SELECT id, email, password_hash, role, is_active FROM users WHERE id = $1`

	err := r.pg.GetContext(ctx, &u, query, userID)
	if err != nil {
		return nil, err
	}

	return &u, nil
}
