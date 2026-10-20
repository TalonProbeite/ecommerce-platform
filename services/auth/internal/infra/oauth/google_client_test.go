package oauth

import (
	"context"
	"crypto/rand"
	"crypto/rsa"
	"encoding/base64"
	"encoding/json"
	"errors"
	"github.com/golang-jwt/jwt/v5"
	"golang.org/x/oauth2"
	"math/big"
	"net/http"
	"net/http/httptest"
	"net/url"
	"shop/auth/internal/domain"
	"strings"
	"testing"
	"time"
)

func TestGoogleAuthURLUsesPKCEAndNonce(t *testing.T) {
	client := NewGoogleClient(&oauth2.Config{ClientID: "client", RedirectURL: "https://rentify.test/auth/api/oauth/google/callback", Scopes: []string{"openid", "email", "profile"}, Endpoint: oauth2.Endpoint{AuthURL: "https://accounts.google.com/auth"}})
	verifier := oauth2.GenerateVerifier()
	parsed, err := url.Parse(client.AuthURL("state", verifier, "nonce"))
	if err != nil {
		t.Fatal(err)
	}
	q := parsed.Query()
	if q.Get("state") != "state" || q.Get("nonce") != "nonce" || q.Get("code_challenge_method") != "S256" || q.Get("code_challenge") != oauth2.S256ChallengeFromVerifier(verifier) {
		t.Fatalf("authorization query: %v", q)
	}
}

// Replace only discovery transport; the verifier still validates Google's
// issuer, signature, audience, expiry and nonce against a real signed token.
type discoveryTransport struct {
	base      http.RoundTripper
	discovery string
}

func (t discoveryTransport) RoundTrip(req *http.Request) (*http.Response, error) {
	if req.URL.String() == "https://accounts.google.com/.well-known/openid-configuration" {
		copy := req.Clone(req.Context())
		u, err := url.Parse(t.discovery)
		if err != nil {
			return nil, err
		}
		copy.URL = u
		return t.base.RoundTrip(copy)
	}
	return t.base.RoundTrip(req)
}
func TestGoogleProfileVerifiesNonceAndClaims(t *testing.T) {
	key, err := rsa.GenerateKey(rand.Reader, 2048)
	if err != nil {
		t.Fatal(err)
	}
	verifier := oauth2.GenerateVerifier()
	nonce := "expected-nonce"
	audience := "client"
	subject := "google-sub"
	var server *httptest.Server
	server = httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/json")
		switch r.URL.Path {
		case "/discovery":
			_ = json.NewEncoder(w).Encode(map[string]any{"issuer": "https://accounts.google.com", "authorization_endpoint": server.URL + "/authorize", "token_endpoint": server.URL + "/token", "jwks_uri": server.URL + "/keys", "id_token_signing_alg_values_supported": []string{"RS256"}})
		case "/keys":
			_ = json.NewEncoder(w).Encode(map[string]any{"keys": []map[string]string{{"kty": "RSA", "kid": "test-key", "alg": "RS256", "use": "sig", "n": base64.RawURLEncoding.EncodeToString(key.N.Bytes()), "e": base64.RawURLEncoding.EncodeToString(big.NewInt(int64(key.E)).Bytes())}}})
		case "/token":
			if err := r.ParseForm(); err != nil {
				t.Error(err)
			}
			if r.Form.Get("code_verifier") != verifier {
				t.Error("token exchange omitted PKCE verifier")
			}
			token := jwt.NewWithClaims(jwt.SigningMethodRS256, jwt.MapClaims{"iss": "https://accounts.google.com", "aud": audience, "sub": subject, "exp": time.Now().Add(time.Minute).Unix(), "iat": time.Now().Unix(), "nonce": nonce, "email": "user@gmail.com", "email_verified": true, "given_name": "Jane", "family_name": "Doe"})
			token.Header["kid"] = "test-key"
			signed, err := token.SignedString(key)
			if err != nil {
				t.Error(err)
			}
			_ = json.NewEncoder(w).Encode(map[string]any{"access_token": "access", "token_type": "Bearer", "id_token": signed})
		default:
			http.NotFound(w, r)
		}
	}))
	t.Cleanup(server.Close)
	httpClient := &http.Client{Transport: discoveryTransport{base: http.DefaultTransport, discovery: server.URL + "/discovery"}}
	ctx := context.WithValue(context.Background(), oauth2.HTTPClient, httpClient)
	client := NewGoogleClient(&oauth2.Config{ClientID: "client", ClientSecret: "secret", Endpoint: oauth2.Endpoint{TokenURL: server.URL + "/token"}})
	profile, err := client.GetProfile(ctx, "code", verifier, "expected-nonce")
	if err != nil {
		t.Fatal(err)
	}
	if profile.ProviderUserID != "google-sub" || profile.Provider != domain.ProviderGoogle || !profile.EmailVerified {
		t.Fatalf("claims: %+v", profile)
	}
	nonce = "wrong"
	if _, err := client.GetProfile(ctx, "code", verifier, "expected-nonce"); !errors.Is(err, domain.ErrOAuthStateInvalid) {
		t.Fatalf("nonce: %v", err)
	}
	nonce = "expected-nonce"
	audience = "another-client"
	if _, err := client.GetProfile(ctx, "code", verifier, "expected-nonce"); err == nil || !strings.Contains(err.Error(), "audience") {
		t.Fatalf("audience: %v", err)
	}
}
