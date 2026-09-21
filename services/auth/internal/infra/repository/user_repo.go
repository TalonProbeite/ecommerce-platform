package repository

import (
	"context"
	"database/sql"
	"errors"
	"fmt"
	"shop/auth/internal/domain"

	"github.com/google/uuid"
	"github.com/jmoiron/sqlx"
)

type UserRepo struct {
	pg *sqlx.DB
}

func NewUserRepo(pg *sqlx.DB) *UserRepo {
	return &UserRepo{pg: pg}
}

func (r *UserRepo) GetByEmail(ctx context.Context, email string) (*domain.User, error) {
	var u domain.User

	query := `SELECT id, email, password_hash, role, is_active FROM users WHERE email = $1`

	err := r.pg.GetContext(ctx, &u, query, email)
	if err != nil {
		if errors.Is(err, sql.ErrNoRows) {
			return nil, domain.ErrUserNotFound
		}
		return nil, err
	}

	return &u, nil
}

func (r *UserRepo) Create(ctx context.Context, u *domain.User) (userID uuid.UUID, err error) {
	if u.Role == "" {
		u.Role = domain.RoleCustomer
	}

	userID = uuid.Must(uuid.NewV7())
	profileID := uuid.Must(uuid.NewV7())
	u.ID = userID.String()

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
		INSERT INTO users (id, email, password_hash, role, is_active, is_email_verified)
		VALUES ($1, $2, $3, $4, $5, $6)
	`
	_, err = tx.ExecContext(
		ctx,
		userQuery,
		userID,
		u.Email,
		u.Password,
		u.Role,
		u.IsActive,
		u.IsEmailVerified,
	)
	if err != nil {
		return uuid.Nil, fmt.Errorf("insert user: %w", err)
	}

	profileQuery := `
		INSERT INTO profiles (id, user_id, first_name, last_name, phone)
		VALUES ($1, $2, $3, $4, $5)
	`
	_, err = tx.ExecContext(
		ctx,
		profileQuery,
		profileID,
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

func (r *UserRepo) GetStatus(ctx context.Context, userID string) (bool, error) {
	query := `SELECT is_active FROM users WHERE id = $1`
	var isActive bool

	if err := r.pg.GetContext(ctx, &isActive, query, userID); err != nil {
		return false, fmt.Errorf("get status by id: %w", err)
	}

	return isActive, nil
}

func (r *UserRepo) SetVerified(
	ctx context.Context,
	userID string,
) (email, firstName string, err error) {
	query := `
		UPDATE users u
		SET is_email_verified = true
		FROM profiles p
		WHERE u.id = $1 AND p.user_id = u.id
		RETURNING u.email, p.first_name
	`

	err = r.pg.QueryRowContext(ctx, query, userID).Scan(&email, &firstName)
	if err != nil {
		if errors.Is(err, sql.ErrNoRows) {
			return "", "", domain.ErrUserNotFound
		}
		return "", "", fmt.Errorf("failed to update email confirmation field: %w", err)
	}

	return email, firstName, nil
}

func (r *UserRepo) GetByID(ctx context.Context, userID string) (*domain.User, error) {
	var u domain.User

	query := `SELECT id, email, password_hash, role, is_active FROM users WHERE id = $1`

	err := r.pg.GetContext(ctx, &u, query, userID)
	if err != nil {
		if errors.Is(err, sql.ErrNoRows) {
			return nil, domain.ErrUserNotFound
		}
		return nil, err
	}

	return &u, nil
}

func (r *UserRepo) CreateWithOauth(ctx context.Context, u *domain.User) (userID uuid.UUID, err error) {
	if u.Role == "" {
		u.Role = domain.RoleCustomer
	}

	userID = uuid.Must(uuid.NewV7())
	profileID := uuid.Must(uuid.NewV7())
	oauthAccountsID := uuid.Must(uuid.NewV7())
	u.ID = userID.String()

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
		INSERT INTO users (id, email, role, is_active, is_email_verified)
		VALUES ($1, $2, $3, $4, $5)
	`
	_, err = tx.ExecContext(
		ctx,
		userQuery,
		userID,
		u.Email,
		u.Role,
		u.IsActive,
		u.IsEmailVerified,
	)
	if err != nil {
		return uuid.Nil, fmt.Errorf("insert user: %w", err)
	}

	profileQuery := `
		INSERT INTO profiles (id, user_id, first_name, last_name, phone)
		VALUES ($1, $2, $3, $4, $5)
	`
	_, err = tx.ExecContext(
		ctx,
		profileQuery,
		profileID,
		u.ID,
		u.FirstName,
		u.LastName,
		u.Phone,
	)
	if err != nil {
		return uuid.Nil, fmt.Errorf("insert profile: %w", err)
	}

	oauthAccountsQuery := `
	INSERT INTO oauth_accounts (id , user_id, provider, provider_user_id)
	VALUES ($1, $2, $3, $4)
	`

	_, err = tx.ExecContext(
		ctx,
		oauthAccountsQuery,
		oauthAccountsID,
		userID,
		u.Provider,
		u.ProviderUserID,
	)

	if err := tx.Commit(); err != nil {
		return uuid.Nil, fmt.Errorf("commit tx: %w", err)
	}

	return userID, nil
}

func (r *UserRepo) GetByOAuth(
	ctx context.Context,
	provider string,
	providerUserID string,
) (*domain.User, error) {
	var u domain.User

	const query = `
        SELECT
            u.id,
            u.email,
            u.password_hash,
            u.role,
            u.is_active
        FROM users u
        JOIN oauth_accounts o ON o.user_id = u.id
        WHERE o.provider = $1
          AND o.provider_user_id = $2
    `

	err := r.pg.GetContext(ctx, &u, query, provider, providerUserID)
	if err != nil {
		if errors.Is(err, sql.ErrNoRows) {
			return nil, domain.ErrUserNotFound
		}

		return nil, err
	}

	return &u, nil
}

func (r *UserRepo) GetEmailByUserID(
	ctx context.Context,
	userID string,
) (string, error) {
	const query = `SELECT email FROM users WHERE id = $1`

	var email string

	err := r.pg.GetContext(
		ctx,
		&email,
		query,
		userID,
	)
	if err != nil {
		return "", fmt.Errorf("failed to receive email: %w", err)
	}

	return email, nil
}

func (r *UserRepo) GetByIDProfile(ctx context.Context, userID string) (*domain.User, error) {
	var u domain.User

	const query = `SELECT
						email ,
						is_active ,
						first_name,
						last_name,
						phone
					FROM users u
					JOIN profiles p ON u.id = p.user_id
					WHERE u.id = $1`
	err := r.pg.GetContext(ctx, &u, query, userID)
	if err != nil {
		return &domain.User{}, err
	}

	return &u, nil
}
