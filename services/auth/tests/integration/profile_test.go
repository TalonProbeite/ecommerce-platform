package integration_test

import (
	"encoding/json"
	"fmt"
	"net/http"
	"strings"
	"testing"
	"time"
)

const (
	profilePath         = "/api/private/profile"
	profilePasswordPath = "/api/private/profile/password"
)

type profileResponse struct {
	Email     string `json:"email"`
	FirstName string `json:"first_name"`
	LastName  string `json:"last_name"`
	Phone     string `json:"phone"`
}

func TestProfileUnauthorized(t *testing.T) {
	requireIntegration(t)

	tests := []struct {
		name   string
		method string
		path   string
		body   any
	}{
		{
			name:   "get profile",
			method: http.MethodGet,
			path:   profilePath,
		},
		{
			name:   "patch profile",
			method: http.MethodPatch,
			path:   profilePath,
			body:   map[string]string{"first_name": "Jane"},
		},
		{
			name:   "reset password",
			method: http.MethodPut,
			path:   profilePasswordPath,
			body: map[string]string{
				"old_pass": "Password123!",
				"new_pass": "NewPassword123!",
			},
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			client := newTestClient(t)

			result := doJSON(t, client, tt.method, tt.path, tt.body, nil)
			assert4xx(t, result, tt.name)
		})
	}
}

func TestProfileGet(t *testing.T) {
	requireIntegration(t)

	client := newTestClient(t)
	creds := newCredentials()

	registered := registerUser(t, client, creds)
	assert2xx(t, registered, "registration")

	access := findCookie(registered.Cookies, "access_token")
	refresh := findCookie(registered.Cookies, "refresh_token")
	if access == nil || refresh == nil {
		t.Fatalf("registration did not return auth cookies: cookies=%v", registered.Cookies)
	}

	result := doRequest(
		t,
		newTestClient(t),
		http.MethodGet,
		profilePath,
		nil,
		headersWithCookies(nil, access, refresh),
	)
	assert2xx(t, result, "get profile")

	var profile profileResponse
	if err := json.Unmarshal(result.Body, &profile); err != nil {
		t.Fatalf("decode profile response: %v; body=%s", err, result.Body)
	}

	if profile.Email != creds.Email ||
		profile.FirstName != "Integration" ||
		profile.LastName != "Test" ||
		profile.Phone != "+31612345678" {
		t.Fatalf("unexpected profile: %+v", profile)
	}
}

func TestProfilePatch(t *testing.T) {
	requireIntegration(t)

	client := newTestClient(t)
	creds := newCredentials()

	registered := registerUser(t, client, creds)
	assert2xx(t, registered, "registration")

	access := findCookie(registered.Cookies, "access_token")
	refresh := findCookie(registered.Cookies, "refresh_token")
	if access == nil || refresh == nil {
		t.Fatalf("registration did not return auth cookies: cookies=%v", registered.Cookies)
	}

	patchResult := doJSON(
		t,
		newTestClient(t),
		http.MethodPatch,
		profilePath,
		map[string]string{
			"first_name": "Jane",
			"last_name":  "Smith",
			"phone":      "+48123456789",
		},
		headersWithCookies(nil, access, refresh),
	)
	assert2xx(t, patchResult, "patch profile")

	var message map[string]string
	if err := json.Unmarshal(patchResult.Body, &message); err != nil {
		t.Fatalf("decode patch response: %v; body=%s", err, patchResult.Body)
	}
	if message["message"] != "profile updated successfully" {
		t.Fatalf("unexpected patch response: %s", patchResult.Body)
	}

	getResult := doRequest(
		t,
		newTestClient(t),
		http.MethodGet,
		profilePath,
		nil,
		headersWithCookies(nil, access, refresh),
	)
	assert2xx(t, getResult, "get updated profile")

	var profile profileResponse
	if err := json.Unmarshal(getResult.Body, &profile); err != nil {
		t.Fatalf("decode updated profile response: %v; body=%s", err, getResult.Body)
	}

	if profile.Email != creds.Email ||
		profile.FirstName != "Jane" ||
		profile.LastName != "Smith" ||
		profile.Phone != "+48123456789" {
		t.Fatalf("unexpected updated profile: %+v", profile)
	}

	newEmail := fmt.Sprintf("profile-email-%d@example.com", time.Now().UnixNano())
	emailPatch := doJSON(
		t,
		newTestClient(t),
		http.MethodPatch,
		profilePath,
		map[string]string{"email": newEmail},
		headersWithCookies(nil, access, refresh),
	)
	assert2xx(t, emailPatch, "patch profile email")

	getEmailResult := doRequest(
		t,
		newTestClient(t),
		http.MethodGet,
		profilePath,
		nil,
		headersWithCookies(nil, access, refresh),
	)
	assert2xx(t, getEmailResult, "get profile after email update")

	if err := json.Unmarshal(getEmailResult.Body, &profile); err != nil {
		t.Fatalf("decode profile after email update: %v; body=%s", err, getEmailResult.Body)
	}
	if profile.Email != newEmail {
		t.Fatalf("email = %q, want %q", profile.Email, newEmail)
	}
}

func TestProfilePatchValidation(t *testing.T) {
	requireIntegration(t)

	client := newTestClient(t)
	creds := newCredentials()
	registered := registerUser(t, client, creds)
	assert2xx(t, registered, "registration")

	access := findCookie(registered.Cookies, "access_token")
	refresh := findCookie(registered.Cookies, "refresh_token")
	if access == nil || refresh == nil {
		t.Fatalf("registration did not return auth cookies: cookies=%v", registered.Cookies)
	}

	tests := []struct {
		name    string
		body    []byte
		expects string
	}{
		{
			name:    "malformed json",
			body:    []byte(`{"first_name":`),
			expects: "invalid request format",
		},
		{
			name:    "empty patch",
			body:    []byte(`{}`),
			expects: "invalid data, all fields are empty",
		},
		{
			name:    "empty field",
			body:    []byte(`{"first_name":""}`),
			expects: "first name cannot be empty",
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			result := doRequest(
				t,
				newTestClient(t),
				http.MethodPatch,
				profilePath,
				tt.body,
				headersWithCookies(
					map[string]string{"Content-Type": "application/json"},
					access,
					refresh,
				),
			)
			if result.Status != http.StatusBadRequest {
				t.Fatalf("expected 400, got status=%d body=%s", result.Status, result.Body)
			}
			if tt.expects != "" && !strings.Contains(string(result.Body), tt.expects) {
				t.Fatalf("response body=%s does not contain %q", result.Body, tt.expects)
			}
		})
	}
}

func TestProfileResetPassword(t *testing.T) {
	requireIntegration(t)

	client := newTestClient(t)
	creds := newCredentials()
	registered := registerUser(t, client, creds)
	assert2xx(t, registered, "registration")

	access := findCookie(registered.Cookies, "access_token")
	refresh := findCookie(registered.Cookies, "refresh_token")
	if access == nil || refresh == nil {
		t.Fatalf("registration did not return auth cookies: cookies=%v", registered.Cookies)
	}

	newPassword := "NewPassword123!"
	resetResult := doJSON(
		t,
		newTestClient(t),
		http.MethodPut,
		profilePasswordPath,
		map[string]string{
			"old_pass": creds.Password,
			"new_pass": newPassword,
		},
		headersWithCookies(nil, access, refresh),
	)
	assert2xx(t, resetResult, "reset password")

	var message map[string]string
	if err := json.Unmarshal(resetResult.Body, &message); err != nil {
		t.Fatalf("decode reset response: %v; body=%s", err, resetResult.Body)
	}
	if message["message"] != "password has been successfully reset" {
		t.Fatalf("unexpected reset response: %s", resetResult.Body)
	}

	accessDelete := responseCookie(resetResult, "access_token")
	refreshDelete := responseCookie(resetResult, "refresh_token")
	if accessDelete.Value != "" || accessDelete.MaxAge >= 0 {
		t.Fatalf("access cookie was not deleted: %+v", accessDelete)
	}
	if refreshDelete.Value != "" || refreshDelete.MaxAge >= 0 {
		t.Fatalf("refresh cookie was not deleted: %+v", refreshDelete)
	}

	oldRefreshResult := doRequest(
		t,
		newTestClient(t),
		http.MethodPost,
		refreshPath,
		nil,
		headersWithCookies(nil, access, refresh),
	)
	assert4xx(t, oldRefreshResult, "refresh after password reset")

	oldLogin := doJSON(
		t,
		newTestClient(t),
		http.MethodPost,
		loginPath,
		map[string]string{
			"email":    creds.Email,
			"password": creds.Password,
		},
		nil,
	)
	assert4xx(t, oldLogin, "login with old password")

	newLogin := doJSON(
		t,
		newTestClient(t),
		http.MethodPost,
		loginPath,
		map[string]string{
			"email":    creds.Email,
			"password": newPassword,
		},
		nil,
	)
	assert2xx(t, newLogin, "login with new password")

	newAccess := findCookie(newLogin.Cookies, "access_token")
	newRefresh := findCookie(newLogin.Cookies, "refresh_token")
	if newAccess == nil || newRefresh == nil {
		t.Fatalf("login after password reset did not return auth cookies: cookies=%v", newLogin.Cookies)
	}
}

func TestProfileResetPasswordValidation(t *testing.T) {
	requireIntegration(t)

	client := newTestClient(t)
	creds := newCredentials()
	registered := registerUser(t, client, creds)
	assert2xx(t, registered, "registration")

	access := findCookie(registered.Cookies, "access_token")
	refresh := findCookie(registered.Cookies, "refresh_token")
	if access == nil || refresh == nil {
		t.Fatalf("registration did not return auth cookies: cookies=%v", registered.Cookies)
	}

	malformed := doRequest(
		t,
		newTestClient(t),
		http.MethodPut,
		profilePasswordPath,
		[]byte(`{"old_pass":`),
		headersWithCookies(
			map[string]string{"Content-Type": "application/json"},
			access,
			refresh,
		),
	)
	if malformed.Status != http.StatusBadRequest {
		t.Fatalf("expected malformed reset to return 400, got status=%d body=%s", malformed.Status, malformed.Body)
	}

	missingField := doJSON(
		t,
		newTestClient(t),
		http.MethodPut,
		profilePasswordPath,
		map[string]string{"old_pass": creds.Password},
		headersWithCookies(nil, access, refresh),
	)
	if missingField.Status != http.StatusUnprocessableEntity {
		t.Fatalf("expected missing password field to return 422, got status=%d body=%s", missingField.Status, missingField.Body)
	}

	invalidOldPassword := doJSON(
		t,
		newTestClient(t),
		http.MethodPut,
		profilePasswordPath,
		map[string]string{
			"old_pass": "WrongPassword123!",
			"new_pass": "NewPassword123!",
		},
		headersWithCookies(nil, access, refresh),
	)
	if invalidOldPassword.Status != http.StatusUnauthorized {
		t.Fatalf("expected invalid old password to return 401, got status=%d body=%s", invalidOldPassword.Status, invalidOldPassword.Body)
	}
}

func responseCookie(result httpResult, name string) *http.Cookie {
	for _, cookie := range result.Cookies {
		if cookie.Name == name {
			return cookie
		}
	}
	panic("cookie not found: " + name)
}
