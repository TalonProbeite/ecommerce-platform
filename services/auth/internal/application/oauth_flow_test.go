package application

import (
	"errors"
	"shop/auth/internal/domain"
	"testing"
)

func TestOAuthRegistrationCreatesActiveUser(t *testing.T) {
	f := newFixture(t)
	f.oauthMissing = true
	f.store["oauth:pending:key"] = mustJSON(t, f.profile)
	_, err := f.svc.CompleteOAuthRegistration(testCtx(), domain.OAuthRegistrationData{Key: "key", Phone: "+48123456789"})
	if err != nil {
		t.Fatal(err)
	}
	if !f.createdUser.IsActive {
		t.Fatal("OAuth registration created an inactive user")
	}
}

func TestOAuthCallbackRejectsBlockedAccount(t *testing.T) {
	f := newFixture(t)
	f.store["state:st"] = mustJSON(t, domain.OAuthTransaction{Verifier: "verifier", Nonce: "nonce"})
	f.user.IsActive = false
	_, err := f.svc.GoogleCallback(testCtx(), "st", "code", "st")
	if !errors.Is(err, domain.ErrUserNotActive) {
		t.Fatalf("expected inactive account error, got %v", err)
	}
	if f.created != nil {
		t.Fatal("blocked account received a session")
	}
}

func TestPendingOAuthProfileReadDoesNotConsume(t *testing.T) {
	f := newFixture(t)
	f.oauthMissing = true
	f.store["oauth:pending:key"] = mustJSON(t, f.profile)
	for range 2 {
		p, err := f.svc.GetOAuthProfile(testCtx(), "key")
		if err != nil {
			t.Fatal(err)
		}
		if p.Email != f.profile.Email || p.FirstName != f.profile.FirstName {
			t.Fatalf("wrong profile: %+v", p)
		}
	}
	if len(f.deleted) != 0 || len(f.saved) != 0 {
		t.Fatal("read changed pending registration")
	}
}

func TestPendingOAuthProfileExpired(t *testing.T) {
	f := newFixture(t)
	_, err := f.svc.GetOAuthProfile(testCtx(), "missing")
	if !errors.Is(err, domain.ErrOAuthRegistrationExpired) {
		t.Fatalf("got %v", err)
	}
}

func TestOAuthRegistrationRejectsInvalidForm(t *testing.T) {
	f := newFixture(t)
	f.oauthMissing = true
	f.store["oauth:pending:key"] = mustJSON(t, f.profile)
	_, err := f.svc.CompleteOAuthRegistration(testCtx(), domain.OAuthRegistrationData{Key: "key", Phone: " "})
	if !errors.Is(err, domain.ErrValidationFailed) {
		t.Fatalf("got %v", err)
	}
	if f.createdUser != nil {
		t.Fatal("invalid form created a user")
	}
}

func TestAccountProfileIncludesOAuthFlag(t *testing.T) {
	f := newProfileFixture(t)
	f.user.IsOAuth = true
	p, err := f.svc.GetUserProfile(testCtx(), testUserID)
	if err != nil {
		t.Fatal(err)
	}
	if !p.IsOAuth {
		t.Fatal("OAuth profile flag was lost")
	}
}

func TestOAuthCallbackBindingAndReplay(t *testing.T) {
	f := newFixture(t)
	f.store["state:st"] = mustJSON(t, domain.OAuthTransaction{Verifier: "verifier", Nonce: "nonce"})
	if _, err := f.svc.GoogleCallback(testCtx(), "st", "code", "other"); !errors.Is(err, domain.ErrOAuthStateInvalid) {
		t.Fatalf("foreign browser: %v", err)
	}
	if _, ok := f.store["state:st"]; !ok {
		t.Fatal("foreign browser consumed state")
	}
	if _, err := f.svc.GoogleCallback(testCtx(), "st", "code", "st"); err != nil {
		t.Fatal(err)
	}
	if _, err := f.svc.GoogleCallback(testCtx(), "st", "code", "st"); !errors.Is(err, domain.ErrOAuthStateInvalid) {
		t.Fatalf("replay: %v", err)
	}
}

func TestOAuthRegistrationSessionFailureCanRetry(t *testing.T) {
	f := newFixture(t)
	f.oauthMissing = true
	f.store["oauth:pending:key"] = mustJSON(t, f.profile)
	f.fail["sess.CreateSession"] = errBoom
	data := domain.OAuthRegistrationData{Key: "key", Phone: "+48123456789"}
	if _, err := f.svc.CompleteOAuthRegistration(testCtx(), data); !errors.Is(err, errBoom) {
		t.Fatalf("first attempt: %v", err)
	}
	if _, ok := f.store["oauth:pending:key"]; !ok {
		t.Fatal("failure consumed profile")
	}
	f.oauthMissing = false
	delete(f.fail, "sess.CreateSession")
	if _, err := f.svc.CompleteOAuthRegistration(testCtx(), data); err != nil {
		t.Fatal(err)
	}
	count := 0
	for _, call := range f.calls {
		if call == "users.CreateWithOauth" {
			count++
		}
	}
	if count != 1 {
		t.Fatalf("created account %d times", count)
	}
}

func TestOAuthRegistrationBusy(t *testing.T) {
	f := newFixture(t)
	f.store["oauth:lock:key"] = "owner"
	_, err := f.svc.CompleteOAuthRegistration(testCtx(), domain.OAuthRegistrationData{Key: "key", Phone: "+48123456789"})
	if !errors.Is(err, domain.ErrOAuthRegistrationBusy) {
		t.Fatalf("got %v", err)
	}
	if f.createdUser != nil {
		t.Fatal("busy registration created user")
	}
}

func TestOAuthRegistrationCanClearOptionalSurname(t *testing.T) {
	f := newFixture(t)
	f.oauthMissing = true
	f.store["oauth:pending:key"] = mustJSON(t, f.profile)
	_, err := f.svc.CompleteOAuthRegistration(testCtx(), domain.OAuthRegistrationData{Key: "key", Phone: "+48123456789", LastNameProvided: true})
	if err != nil {
		t.Fatal(err)
	}
	if f.createdUser.LastName != "" {
		t.Fatalf("surname was not cleared: %q", f.createdUser.LastName)
	}
}

func TestOAuthRegistrationEmailVerificationPolicy(t *testing.T) {
	for _, tc := range []struct {
		email, hd      string
		verified, want bool
	}{
		{"user@gmail.com", "", true, true}, {"user@company.test", "company.test", true, true}, {"user@example.com", "", true, false}, {"user@gmail.com", "", false, false},
	} {
		t.Run(tc.email+tc.hd, func(t *testing.T) {
			f := newFixture(t)
			f.oauthMissing = true
			f.profile.Email = tc.email
			f.profile.HostedDomain = tc.hd
			f.profile.EmailVerified = tc.verified
			f.store["oauth:pending:key"] = mustJSON(t, f.profile)
			_, err := f.svc.CompleteOAuthRegistration(testCtx(), domain.OAuthRegistrationData{Key: "key", Phone: "+48123456789"})
			if err != nil {
				t.Fatal(err)
			}
			if f.createdUser.IsEmailVerified != tc.want {
				t.Fatalf("verified: %v want %v", f.createdUser.IsEmailVerified, tc.want)
			}
		})
	}
}
func TestOAuthRegistrationEmailConflict(t *testing.T) {
	f := newFixture(t)
	f.oauthMissing = true
	f.store["oauth:pending:key"] = mustJSON(t, f.profile)
	f.fail["users.CreateWithOauth"] = domain.ErrEmailAlreadyExists
	_, err := f.svc.CompleteOAuthRegistration(testCtx(), domain.OAuthRegistrationData{Key: "key", Phone: "+48123456789"})
	if !errors.Is(err, domain.ErrEmailAlreadyExists) {
		t.Fatalf("conflict: %v", err)
	}
	if f.created != nil {
		t.Fatal("conflict issued session")
	}
}
func TestOAuthRegistrationExplicitEmptyFirstNameRejected(t *testing.T) {
	f := newFixture(t)
	f.oauthMissing = true
	f.store["oauth:pending:key"] = mustJSON(t, f.profile)
	_, err := f.svc.CompleteOAuthRegistration(testCtx(), domain.OAuthRegistrationData{Key: "key", Phone: "+48123456789", FirstNameProvided: true})
	if !errors.Is(err, domain.ErrValidationFailed) {
		t.Fatalf("validation: %v", err)
	}
}
