package integration_test

import (
	"crypto/tls"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"net/http/cookiejar"
	"os"
	"strings"
	"testing"
	"time"
)

const (
	defaultBaseURL = "https://localhost:8443"

	registerPath = "/api/register"
	loginPath    = "/api/login"
	refreshPath  = "/api/refresh"
	logoutPath   = "/api/logout"
	healthPath   = "/api/healthz"
)

type authCredentials struct {
	Email    string
	Password string
}

type httpResult struct {
	Status  int
	Body    []byte
	Cookies []*http.Cookie
}

func TestMain(m *testing.M) {
	code := m.Run()
	os.Exit(code)
}

func TestAuthHealth(t *testing.T) {
	requireIntegration(t)

	client := newTestClient(t)
	result := doRequest(t, client, http.MethodGet, healthPath, nil, nil)

	if result.Status < http.StatusOK || result.Status >= http.StatusMultipleChoices {
		t.Fatalf("health check failed: status=%d body=%s", result.Status, result.Body)
	}

	t.Logf("health check passed: status=%d", result.Status)
}

func TestAuthRegisterDuplicateEmail(t *testing.T) {
	requireIntegration(t)

	client := newTestClient(t)
	creds := newCredentials()

	first := registerUser(t, client, creds)
	assert2xx(t, first, "first registration")

	second := doJSON(
		t,
		client,
		http.MethodPost,
		registerPath,
		registerPayload(creds),
		nil,
	)

	if second.Status < http.StatusBadRequest || second.Status >= http.StatusInternalServerError {
		t.Fatalf(
			"expected duplicate registration to return 4xx, got status=%d body=%s",
			second.Status,
			second.Body,
		)
	}

	t.Logf("duplicate registration rejected: status=%d body=%s", second.Status, second.Body)
}

func TestAuthLoginInvalidPassword(t *testing.T) {
	requireIntegration(t)

	client := newTestClient(t)
	creds := newCredentials()

	registered := registerUser(t, client, creds)
	assert2xx(t, registered, "registration")

	result := doJSON(
		t,
		client,
		http.MethodPost,
		loginPath,
		map[string]string{
			"email":    creds.Email,
			"password": "DefinitelyWrongPassword123!",
		},
		nil,
	)

	if result.Status < http.StatusBadRequest || result.Status >= http.StatusInternalServerError {
		t.Fatalf(
			"expected invalid login to return 4xx, got status=%d body=%s",
			result.Status,
			result.Body,
		)
	}

	t.Logf("invalid login rejected: status=%d body=%s", result.Status, result.Body)
}

func TestAuthRegisterMalformedJSON(t *testing.T) {
	requireIntegration(t)

	client := newTestClient(t)

	result := doRequest(
		t,
		client,
		http.MethodPost,
		registerPath,
		[]byte(`{"email":`),
		headers(map[string]string{"Content-Type": "application/json"}),
	)

	if result.Status < http.StatusBadRequest || result.Status >= http.StatusInternalServerError {
		t.Fatalf(
			"expected malformed JSON to return 4xx, got status=%d body=%s",
			result.Status,
			result.Body,
		)
	}

	t.Logf("malformed request rejected: status=%d body=%s", result.Status, result.Body)
}

func TestAuthFullFlow_RegisterLoginRefreshLogout(t *testing.T) {
	requireIntegration(t)

	client := newTestClient(t)
	creds := newCredentials()

	t.Logf("step=register email=%s", creds.Email)
	registered := registerUser(t, client, creds)
	assert2xx(t, registered, "registration")

	registerAccess := findCookie(registered.Cookies, "access_token")
	registerRefresh := findCookie(registered.Cookies, "refresh_token")

	if registerAccess == nil {
		t.Fatalf("registration did not return access_token cookie; status=%d body=%s", registered.Status, registered.Body)
	}
	if registerRefresh == nil {
		t.Fatalf("registration did not return refresh_token cookie; status=%d body=%s", registered.Status, registered.Body)
	}

	t.Logf("registration returned access and refresh cookies")

	// Use a fresh client so Login is tested independently from the cookies
	// produced by Registration.
	loginClient := newTestClient(t)

	t.Logf("step=login email=%s", creds.Email)
	loginResult := doJSON(
		t,
		loginClient,
		http.MethodPost,
		loginPath,
		map[string]string{
			"email":    creds.Email,
			"password": creds.Password,
		},
		nil,
	)
	assert2xx(t, loginResult, "login")

	loginAccess := findCookie(loginResult.Cookies, "access_token")
	loginRefresh := findCookie(loginResult.Cookies, "refresh_token")

	if loginAccess == nil || loginRefresh == nil {
		t.Fatalf("login did not return both auth cookies: cookies=%v", loginResult.Cookies)
	}

	t.Logf("step=refresh old_refresh=%s", loginRefresh.Value)
	requestClient := newTestClient(t)

	refreshResult := doRequest(
		t,
		requestClient,
		http.MethodPost,
		refreshPath,
		nil,
		headersWithCookies(
			map[string]string{},
			loginAccess,
			loginRefresh,
		),
	)
	assert2xx(t, refreshResult, "refresh")

	newAccess := findCookie(refreshResult.Cookies, "access_token")
	newRefresh := findCookie(refreshResult.Cookies, "refresh_token")

	if newAccess == nil || newRefresh == nil {
		t.Fatalf("refresh did not return both rotated cookies: cookies=%v", refreshResult.Cookies)
	}

	if newRefresh.Value == loginRefresh.Value {
		t.Fatalf("refresh token was not rotated: old=%s new=%s", loginRefresh.Value, newRefresh.Value)
	}

	t.Logf("refresh rotated token old=%s new=%s", loginRefresh.Value, newRefresh.Value)

	// The old refresh token must no longer be usable after rotation.
	t.Logf("step=refresh old rotated-away token")
	oldRefreshResult := doRequest(
		t,
		newTestClient(t),
		http.MethodPost,
		refreshPath,
		nil,
		headersWithCookies(
			map[string]string{},
			loginAccess,
			loginRefresh,
		),
	)

	assert4xx(t, oldRefreshResult, "old refresh token")

	t.Logf("old refresh token rejected: status=%d body=%s", oldRefreshResult.Status, oldRefreshResult.Body)

	t.Logf("step=logout")
	logoutResult := doRequest(
		t,
		requestClient,
		http.MethodPost,
		logoutPath,
		nil,
		headersWithCookies(
			map[string]string{},
			newAccess,
			newRefresh,
		),
	)
	assert2xx(t, logoutResult, "logout")

	// After logout the current refresh token must be gone from Redis.
	t.Logf("step=refresh after logout")
	postLogoutRefresh := doRequest(
		t,
		newTestClient(t),
		http.MethodPost,
		refreshPath,
		nil,
		headersWithCookies(
			map[string]string{},
			newAccess,
			newRefresh,
		),
	)

	assert4xx(t, postLogoutRefresh, "refresh after logout")
	t.Logf("logout invalidated refresh token: status=%d body=%s", postLogoutRefresh.Status, postLogoutRefresh.Body)
}

func registerUser(t *testing.T, client *http.Client, creds authCredentials) httpResult {
	t.Helper()

	return doJSON(
		t,
		client,
		http.MethodPost,
		registerPath,
		registerPayload(creds),
		nil,
	)
}

func registerPayload(creds authCredentials) map[string]string {
	return map[string]string{
		"email":      creds.Email,
		"password":   creds.Password,
		"first_name": "Integration",
		"last_name":  "Test",
		"phone":      "+31612345678",
	}
}

func newCredentials() authCredentials {
	return authCredentials{
		Email:    fmt.Sprintf("integration-%d@example.com", time.Now().UnixNano()),
		Password: "Password123!",
	}
}

func newTestClient(t *testing.T) *http.Client {
	t.Helper()

	jar, err := cookiejar.New(nil)
	if err != nil {
		t.Fatalf("create cookie jar: %v", err)
	}

	transport := &http.Transport{
		TLSClientConfig: &tls.Config{
			InsecureSkipVerify: true, // local self-signed certificate only
		},
	}

	return &http.Client{
		Jar:       jar,
		Transport: transport,
		Timeout:   10 * time.Second,
		CheckRedirect: func(req *http.Request, via []*http.Request) error {
			return http.ErrUseLastResponse
		},
	}
}

func doJSON(
	t *testing.T,
	client *http.Client,
	method string,
	path string,
	payload any,
	headers http.Header,
) httpResult {
	t.Helper()

	body, err := json.Marshal(payload)
	if err != nil {
		t.Fatalf("marshal JSON: %v", err)
	}

	if headers == nil {
		headers = make(http.Header)
	}
	headers.Set("Content-Type", "application/json")

	return doRequest(t, client, method, path, body, headers)
}

func doRequest(
	t *testing.T,
	client *http.Client,
	method string,
	path string,
	body []byte,
	headers http.Header,
) httpResult {
	t.Helper()

	baseURL := os.Getenv("AUTH_BASE_URL")
	if baseURL == "" {
		baseURL = defaultBaseURL
	}

	req, err := http.NewRequest(
		method,
		strings.TrimRight(baseURL, "/")+path,
		strings.NewReader(string(body)),
	)
	if err != nil {
		t.Fatalf("create HTTP request: %v", err)
	}

	for key, values := range headers {
		for _, value := range values {
			req.Header.Add(key, value)
		}
	}

	resp, err := client.Do(req)
	if err != nil {
		t.Fatalf(
			"HTTP request failed: %s %s: %v\nIs the local auth compose stack running?",
			method,
			path,
			err,
		)
	}
	defer resp.Body.Close()

	responseBody, err := io.ReadAll(resp.Body)
	if err != nil {
		t.Fatalf("read HTTP response: %v", err)
	}

	return httpResult{
		Status:  resp.StatusCode,
		Body:    responseBody,
		Cookies: resp.Cookies(),
	}
}

func headers(values map[string]string) http.Header {
	result := make(http.Header)
	for key, value := range values {
		result.Set(key, value)
	}
	return result
}

func headersWithCookies(
	values map[string]string,
	cookies ...*http.Cookie,
) http.Header {
	result := headers(values)

	for _, cookie := range cookies {
		if cookie != nil {
			result.Add("Cookie", cookie.Name+"="+cookie.Value)
		}
	}

	return result
}

func findCookie(cookies []*http.Cookie, name string) *http.Cookie {
	for _, cookie := range cookies {
		if cookie.Name == name && cookie.Value != "" {
			return cookie
		}
	}

	return nil
}

func assert2xx(t *testing.T, result httpResult, operation string) {
	t.Helper()

	if result.Status < http.StatusOK || result.Status >= http.StatusMultipleChoices {
		t.Fatalf(
			"%s failed: expected 2xx, got status=%d body=%s",
			operation,
			result.Status,
			result.Body,
		)
	}
}

func assert4xx(t *testing.T, result httpResult, operation string) {
	t.Helper()

	if result.Status < http.StatusBadRequest || result.Status >= http.StatusInternalServerError {
		t.Fatalf(
			"%s failed: expected 4xx, got status=%d body=%s",
			operation,
			result.Status,
			result.Body,
		)
	}
}

func requireIntegration(t *testing.T) {
	t.Helper()

	if os.Getenv("RUN_INTEGRATION") != "1" {
		t.Skip("integration tests disabled; run with RUN_INTEGRATION=1")
	}
}
