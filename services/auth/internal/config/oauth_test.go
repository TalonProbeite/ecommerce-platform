package config

import "testing"

func TestFrontendOrigin(t *testing.T) {
	for _, origin := range []string{"https://rentify.test", "https://localhost:8443"} {
		cfg := Config{OAuth: OAuthConfig{FrontendURL: origin}}
		if err := cfg.ValidateOAuth(); err != nil {
			t.Fatalf("valid %q: %v", origin, err)
		}
	}
	for _, origin := range []string{"", "http://rentify.test", "https://rentify.test/path", "https://user@rentify.test", "https://rentify.test?next=evil", "https://rentify.test/#fragment", "//rentify.test"} {
		cfg := Config{OAuth: OAuthConfig{FrontendURL: origin}}
		if err := cfg.ValidateOAuth(); err == nil {
			t.Fatalf("accepted %q", origin)
		}
	}
}
