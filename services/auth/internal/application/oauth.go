package application

import (
	"context"
	"crypto/subtle"
	"encoding/json/v2"
	"errors"
	"fmt"
	"net/mail"
	"shop/auth/internal/domain"
	"shop/auth/internal/infra/crypto"
	"shop/shared/roles"
	"strings"
	"time"
	"unicode/utf8"

	"golang.org/x/oauth2"
)

func (as *AuthService) Google(ctx context.Context) (domain.OAuthStart, error) {
	state, err := crypto.GenerateRandomToken(32)
	if err != nil {
		return domain.OAuthStart{}, err
	}
	nonce, err := crypto.GenerateRandomToken(32)
	if err != nil {
		return domain.OAuthStart{}, err
	}
	tx := domain.OAuthTransaction{Verifier: oauth2.GenerateVerifier(), Nonce: nonce}
	data, err := json.Marshal(tx)
	if err != nil {
		return domain.OAuthStart{}, err
	}
	if err := as.sessRepo.SaveEntry(ctx, "state:"+state, string(data), oauthStateTTL); err != nil {
		return domain.OAuthStart{}, fmt.Errorf("error when saving state: %w", err)
	}
	return domain.OAuthStart{URL: as.oauthClient.AuthURL(state, tx.Verifier, tx.Nonce), State: state}, nil
}

func (as *AuthService) GoogleCallback(ctx context.Context, state, code, browserState string) (domain.OAuthResult, error) {
	if code == "" {
		return domain.OAuthResult{}, domain.ErrOAuthStateInvalid
	}
	tx, err := as.consumeOAuthTransaction(ctx, state, browserState)
	if err != nil {
		return domain.OAuthResult{}, err
	}
	profile, err := as.oauthClient.GetProfile(ctx, code, tx.Verifier, tx.Nonce)
	if err != nil {
		return domain.OAuthResult{}, fmt.Errorf("error when retrieving profile: %w", err)
	}
	if !validOAuthIdentity(profile) {
		return domain.OAuthResult{}, domain.ErrOAuthIdentityInvalid
	}
	user, err := as.userRepo.GetByOAuth(ctx, profile.Provider.String(), profile.ProviderUserID)
	if err != nil && !errors.Is(err, domain.ErrUserNotFound) {
		return domain.OAuthResult{}, fmt.Errorf("error verifying user: %w", err)
	}
	if user != nil {
		tokens, sessionErr := as.oauthSession(ctx, user)
		if sessionErr != nil {
			return domain.OAuthResult{}, sessionErr
		}
		return domain.OAuthResult{Tokens: &tokens}, nil
	}
	key, err := crypto.GenerateRandomToken(32)
	if err != nil {
		return domain.OAuthResult{}, err
	}
	profileJSON, err := json.Marshal(profile)
	if err != nil {
		return domain.OAuthResult{}, err
	}
	if err := as.sessRepo.SaveEntry(ctx, "oauth:pending:"+key, string(profileJSON), oauthPendingTTL); err != nil {
		return domain.OAuthResult{}, fmt.Errorf("profile save error: %w", err)
	}
	return domain.OAuthResult{RegistrationKey: key}, nil
}

func validOAuthIdentity(profile *domain.OAuthProfile) bool {
	if profile == nil || profile.Provider != domain.ProviderGoogle || strings.TrimSpace(profile.ProviderUserID) == "" {
		return false
	}
	addr, err := mail.ParseAddress(profile.Email)
	return err == nil && addr.Address == profile.Email && len(profile.Email) <= 255
}

func (as *AuthService) pendingOAuthProfile(ctx context.Context, key string) (domain.OAuthProfile, error) {
	if key == "" {
		return domain.OAuthProfile{}, domain.ErrOAuthRegistrationExpired
	}
	data, err := as.sessRepo.GetValue(ctx, "oauth:pending:"+key)
	if errors.Is(err, domain.ErrEntryNotFound) {
		return domain.OAuthProfile{}, domain.ErrOAuthRegistrationExpired
	}
	if err != nil {
		return domain.OAuthProfile{}, fmt.Errorf("when completing the profile: %w", err)
	}
	var profile domain.OAuthProfile
	if err := json.Unmarshal([]byte(data), &profile); err != nil {
		return profile, fmt.Errorf("deserialization error: %w", err)
	}
	if !validOAuthIdentity(&profile) {
		return profile, domain.ErrOAuthIdentityInvalid
	}
	return profile, nil
}

// GetOAuthProfile reads pending Google data through SessionRepository. It does
// not consume the registration key or extend its lifetime.
func (as *AuthService) GetOAuthProfile(ctx context.Context, key string) (domain.PendingOAuthProfile, error) {
	profile, err := as.pendingOAuthProfile(ctx, key)
	if err != nil {
		return domain.PendingOAuthProfile{}, err
	}
	return domain.PendingOAuthProfile{Email: profile.Email, FirstName: profile.FirstName, LastName: profile.LastName}, nil
}

func (as *AuthService) CompleteOAuthRegistration(ctx context.Context, data domain.OAuthRegistrationData) (tokens domain.TokenPair, err error) {
	ctx, cancel := context.WithTimeout(ctx, 30*time.Second)
	defer cancel()
	if data.Key == "" {
		return domain.TokenPair{}, domain.ErrOAuthRegistrationExpired
	}
	owner, err := crypto.GenerateRandomToken(32)
	if err != nil {
		return domain.TokenPair{}, err
	}
	lockKey := "oauth:lock:" + data.Key
	locked, err := as.sessRepo.AcquireLock(ctx, lockKey, owner, time.Minute)
	if err != nil {
		return domain.TokenPair{}, err
	}
	if !locked {
		return domain.TokenPair{}, domain.ErrOAuthRegistrationBusy
	}
	defer func() {
		// Request cancellation must not prevent lease release; expiry is the fallback.
		releaseCtx, releaseCancel := context.WithTimeout(context.WithoutCancel(ctx), 3*time.Second)
		defer releaseCancel()
		if releaseErr := as.sessRepo.ReleaseLock(releaseCtx, lockKey, owner); releaseErr != nil && err != nil {
			err = errors.Join(err, releaseErr)
		}
		// A successful completion already consumed the pending key; lease expiry
		// safely cleans up any failed release without withholding issued cookies.
	}()
	profile, err := as.pendingOAuthProfile(ctx, data.Key)
	if err != nil {
		return domain.TokenPair{}, err
	}
	user, err := as.completeOAuthUser(ctx, &profile, data)
	if err != nil {
		return domain.TokenPair{}, err
	}
	tokens, err = as.oauthSession(ctx, user)
	if err != nil {
		return domain.TokenPair{}, err
	}
	if err := as.sessRepo.DeleteEntry(ctx, "oauth:pending:"+data.Key); err != nil {
		return domain.TokenPair{}, err
	}
	return tokens, nil
}

// CancelOAuth consumes only the transaction bound to the browser that started it.
func (as *AuthService) CancelOAuth(ctx context.Context, state, browserState string) error {
	if state == "" || browserState == "" || subtle.ConstantTimeCompare([]byte(state), []byte(browserState)) != 1 {
		return domain.ErrOAuthStateInvalid
	}
	_, err := as.sessRepo.ConsumeEntry(ctx, "state:"+state)
	if errors.Is(err, domain.ErrEntryNotFound) {
		return domain.ErrOAuthStateInvalid
	}
	return err
}

func (as *AuthService) completeOAuthUser(ctx context.Context, profile *domain.OAuthProfile, data domain.OAuthRegistrationData) (*domain.User, error) {
	if data.FirstNameProvided || data.FirstName != "" {
		profile.FirstName = strings.TrimSpace(data.FirstName)
	}
	if data.LastNameProvided || data.LastName != "" {
		profile.LastName = strings.TrimSpace(data.LastName)
	}
	profile.FirstName = strings.TrimSpace(profile.FirstName)
	profile.LastName = strings.TrimSpace(profile.LastName)
	phone := strings.TrimSpace(data.Phone)
	if profile.FirstName == "" || utf8.RuneCountInString(profile.FirstName) > 100 || utf8.RuneCountInString(profile.LastName) > 100 || utf8.RuneCountInString(phone) < 10 || utf8.RuneCountInString(phone) > 30 {
		return nil, domain.ErrValidationFailed
	}
	// A previous attempt may have committed the account but failed issuing a
	// session. Recover only by the trusted provider/sub identity, never by email.
	user, err := as.userRepo.GetByOAuth(ctx, profile.Provider.String(), profile.ProviderUserID)
	if err != nil && !errors.Is(err, domain.ErrUserNotFound) {
		return nil, err
	}
	if user == nil {
		return as.createOAuthUser(ctx, profile, phone)
	}
	return user, nil
}

func (as *AuthService) createOAuthUser(ctx context.Context, profile *domain.OAuthProfile, phone string) (*domain.User, error) {
	var user *domain.User

	// Google is authoritative for Gmail and hosted Workspace identities.
	verified := profile.EmailVerified && (strings.HasSuffix(strings.ToLower(profile.Email), "@gmail.com") || profile.HostedDomain != "")
	id, createErr := as.userRepo.CreateWithOauth(ctx, &domain.User{
		Email: profile.Email, FirstName: profile.FirstName, LastName: profile.LastName, Phone: phone,
		Provider: profile.Provider, ProviderUserID: profile.ProviderUserID,
		Role: roles.RoleCustomer, IsActive: true, IsEmailVerified: verified,
	})
	if createErr != nil {
		// Another key for the same Google identity may have won the DB uniqueness
		// race. Only an identity match can recover it; unrelated emails conflict.
		if errors.Is(createErr, domain.ErrEmailAlreadyExists) {
			existing, lookupErr := as.userRepo.GetByOAuth(ctx, profile.Provider.String(), profile.ProviderUserID)
			if lookupErr == nil && existing != nil {
				user = existing
			} else if lookupErr != nil && !errors.Is(lookupErr, domain.ErrUserNotFound) {
				return nil, lookupErr
			}
		}
		if user == nil {
			return nil, fmt.Errorf("error creating oauth user: %w", createErr)
		}
	} else {
		// Only the request that actually created this account publishes welcome.
		// Recovering an existing account after a session failure must not resend it.
		if err := as.publishWelcomeEmail(profile.Email, profile.FirstName); err != nil {
			return nil, err
		}
		user = &domain.User{ID: id.String(), Role: roles.RoleCustomer, IsActive: true}
	}
	return user, nil
}

func (as *AuthService) oauthSession(ctx context.Context, user *domain.User) (domain.TokenPair, error) {
	if !user.IsActive {
		return domain.TokenPair{}, domain.ErrUserNotActive
	}
	return as.CreateSession(ctx, user.ID, user.Role)
}

func (as *AuthService) consumeOAuthTransaction(ctx context.Context, state, browserState string) (domain.OAuthTransaction, error) {
	if state == "" || browserState == "" || subtle.ConstantTimeCompare([]byte(state), []byte(browserState)) != 1 {
		return domain.OAuthTransaction{}, domain.ErrOAuthStateInvalid
	}
	data, err := as.sessRepo.ConsumeEntry(ctx, "state:"+state)
	if errors.Is(err, domain.ErrEntryNotFound) {
		return domain.OAuthTransaction{}, domain.ErrOAuthStateInvalid
	}
	if err != nil {
		return domain.OAuthTransaction{}, fmt.Errorf("error consuming state: %w", err)
	}
	var tx domain.OAuthTransaction
	if decodeErr := json.Unmarshal([]byte(data), &tx); decodeErr != nil || tx.Verifier == "" || tx.Nonce == "" {
		return tx, domain.ErrOAuthStateInvalid
	}
	return tx, nil
}
