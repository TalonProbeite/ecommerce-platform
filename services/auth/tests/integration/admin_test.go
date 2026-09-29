package integration_test

import (
	"encoding/base64"
	"encoding/json"
	"fmt"
	"net/http"
	"os"
	"strings"
	"testing"
)

const (
	adminRolePath = "/api/admin/users/%s/role"
	adminBanPath  = "/api/admin/users/%s/ban"
)

func requireAdminCredentials(t *testing.T) (string, string) {
	t.Helper()

	email := strings.TrimSpace(os.Getenv("ADMIN_EMAIL"))
	password := os.Getenv("ADMIN_PASSWORD")
	if email == "" || password == "" {
		t.Skip("admin integration tests require ADMIN_EMAIL and ADMIN_PASSWORD")
	}
	return email, password
}

func loginAsAdmin(t *testing.T) (*http.Cookie, *http.Cookie) {
	t.Helper()

	email, password := requireAdminCredentials(t)
	result := doJSON(
		t,
		newTestClient(t),
		http.MethodPost,
		loginPath,
		map[string]string{
			"email":    email,
			"password": password,
		},
		nil,
	)
	assert2xx(t, result, "admin login")

	access := findCookie(result.Cookies, "access_token")
	refresh := findCookie(result.Cookies, "refresh_token")
	if access == nil || refresh == nil {
		t.Fatalf("admin login did not return both auth cookies: cookies=%v", result.Cookies)
	}
	return access, refresh
}

func extractUserID(t *testing.T, accessToken string) string {
	t.Helper()

	parts := strings.Split(accessToken, ".")
	if len(parts) != 3 {
		t.Fatalf("access token is not a JWT")
	}

	payload, err := base64.RawURLEncoding.DecodeString(parts[1])
	if err != nil {
		t.Fatalf("decode JWT payload: %v", err)
	}

	var claims map[string]any
	if err := json.Unmarshal(payload, &claims); err != nil {
		t.Fatalf("decode JWT claims: %v", err)
	}

	for _, key := range []string{"user_id", "userID", "userid", "sub", "id"} {
		if value, ok := claims[key].(string); ok && value != "" {
			return value
		}
	}

	t.Fatalf("JWT does not contain a supported user id claim: %v", claims)
	return ""
}

func TestAdminUnauthorized(t *testing.T) {
	requireIntegration(t)

	for name, path := range map[string]string{
		"role": fmt.Sprintf(adminRolePath, testTargetID),
		"ban":  fmt.Sprintf(adminBanPath, testTargetID),
	} {
		t.Run(name, func(t *testing.T) {
			result := doRequest(t, newTestClient(t), http.MethodPatch, path, []byte(`{}`), nil)
			assert4xx(t, result, "unauthorized admin route")
		})
	}
}

func TestAdminRejectsNonAdmin(t *testing.T) {
	requireIntegration(t)

	client := newTestClient(t)
	creds := newCredentials()
	registered := registerUser(t, client, creds)
	assert2xx(t, registered, "customer registration")

	access := findCookie(registered.Cookies, "access_token")
	if access == nil {
		t.Fatalf("customer registration did not return access token")
	}

	for name, tc := range map[string]struct {
		path string
		body string
	}{
		"role": {
			path: fmt.Sprintf(adminRolePath, testTargetID),
			body: `{"role":"analyst"}`,
		},
		"ban": {
			path: fmt.Sprintf(adminBanPath, testTargetID),
			body: `{"is_banned":true}`,
		},
	} {
		t.Run(name, func(t *testing.T) {
			result := doRequest(
				t,
				newTestClient(t),
				http.MethodPatch,
				tc.path,
				[]byte(tc.body),
				headersWithCookies(map[string]string{"Content-Type": "application/json"}, access),
			)
			assert4xx(t, result, "non-admin access")
		})
	}
}

func TestAdminRoleFlow(t *testing.T) {
	requireIntegration(t)

	adminAccess, adminRefresh := loginAsAdmin(t)

	targetClient := newTestClient(t)
	creds := newCredentials()
	registered := registerUser(t, targetClient, creds)
	assert2xx(t, registered, "target registration")

	targetAccess := findCookie(registered.Cookies, "access_token")
	if targetAccess == nil {
		t.Fatalf("target registration did not return access token")
	}
	targetUserID := extractUserID(t, targetAccess.Value)

	rolePath := fmt.Sprintf(adminRolePath, targetUserID)
	adminResult := doJSON(
		t,
		newTestClient(t),
		http.MethodPatch,
		rolePath,
		map[string]string{"role": "admin"},
		headersWithCookies(map[string]string{}, adminAccess, adminRefresh),
	)
	assert2xx(t, adminResult, "admin role update")

	targetLogin := doJSON(
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
	assert2xx(t, targetLogin, "target login after role update")

	targetAdminAccess := findCookie(targetLogin.Cookies, "access_token")
	targetAdminRefresh := findCookie(targetLogin.Cookies, "refresh_token")
	if targetAdminAccess == nil || targetAdminRefresh == nil {
		t.Fatalf("target login did not return both auth cookies")
	}

	verified := doJSON(
		t,
		newTestClient(t),
		http.MethodPatch,
		rolePath,
		map[string]string{"role": "admin"},
		headersWithCookies(map[string]string{}, targetAdminAccess, targetAdminRefresh),
	)
	assert2xx(t, verified, "new admin authorization")
}

func TestAdminRoleValidation(t *testing.T) {
	requireIntegration(t)

	adminAccess, adminRefresh := loginAsAdmin(t)
	path := fmt.Sprintf(adminRolePath, testTargetID)

	tests := []struct {
		name string
		body []byte
	}{
		{name: "malformed json", body: []byte(`{"role":`)},
		{name: "missing role", body: []byte(`{}`)},
		{name: "invalid role", body: []byte(`{"role":"root"}`)},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			result := doRequest(
				t,
				newTestClient(t),
				http.MethodPatch,
				path,
				tt.body,
				headersWithCookies(map[string]string{"Content-Type": "application/json"}, adminAccess, adminRefresh),
			)
			if tt.name == "malformed json" {
				if result.Status != http.StatusBadRequest {
					t.Fatalf("status = %d, want %d: body=%s", result.Status, http.StatusBadRequest, result.Body)
				}
				return
			}
			if result.Status != http.StatusUnprocessableEntity {
				t.Fatalf("status = %d, want %d: body=%s", result.Status, http.StatusUnprocessableEntity, result.Body)
			}
		})
	}
}

func TestAdminBanFlow(t *testing.T) {
	requireIntegration(t)

	adminAccess, adminRefresh := loginAsAdmin(t)

	targetClient := newTestClient(t)
	creds := newCredentials()
	registered := registerUser(t, targetClient, creds)
	assert2xx(t, registered, "target registration")

	targetAccess := findCookie(registered.Cookies, "access_token")
	if targetAccess == nil {
		t.Fatalf("target registration did not return access token")
	}
	targetUserID := extractUserID(t, targetAccess.Value)
	banPath := fmt.Sprintf(adminBanPath, targetUserID)

	banned := doJSON(
		t,
		newTestClient(t),
		http.MethodPatch,
		banPath,
		map[string]bool{"is_banned": true},
		headersWithCookies(map[string]string{}, adminAccess, adminRefresh),
	)
	assert2xx(t, banned, "ban user")

	blockedLogin := doJSON(
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
	assert4xx(t, blockedLogin, "login for banned user")

	unbanned := doJSON(
		t,
		newTestClient(t),
		http.MethodPatch,
		banPath,
		map[string]bool{"is_banned": false},
		headersWithCookies(map[string]string{}, adminAccess, adminRefresh),
	)
	assert2xx(t, unbanned, "unban user")

	restoredLogin := doJSON(
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
	assert2xx(t, restoredLogin, "login after unban")
}

func TestAdminBanValidation(t *testing.T) {
	requireIntegration(t)

	adminAccess, adminRefresh := loginAsAdmin(t)
	path := fmt.Sprintf(adminBanPath, testTargetID)

	tests := []struct {
		name string
		body []byte
	}{
		{name: "malformed json", body: []byte(`{"is_banned":`)},
		{name: "missing flag", body: []byte(`{}`)},
		{name: "null flag", body: []byte(`{"is_banned":null}`)},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			result := doRequest(
				t,
				newTestClient(t),
				http.MethodPatch,
				path,
				tt.body,
				headersWithCookies(map[string]string{"Content-Type": "application/json"}, adminAccess, adminRefresh),
			)
			if tt.name == "malformed json" {
				if result.Status != http.StatusBadRequest {
					t.Fatalf("status = %d, want %d: body=%s", result.Status, http.StatusBadRequest, result.Body)
				}
				return
			}
			if result.Status != http.StatusUnprocessableEntity {
				t.Fatalf("status = %d, want %d: body=%s", result.Status, http.StatusUnprocessableEntity, result.Body)
			}
		})
	}
}

const testTargetID = "11111111-1111-1111-1111-111111111111"
