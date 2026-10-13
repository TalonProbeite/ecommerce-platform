package oauth

import (
	"context"
	"errors"
	"shop/auth/internal/domain"

	"github.com/coreos/go-oidc/v3/oidc"
	"golang.org/x/oauth2"
)

type GoogleClient struct {
	conf *oauth2.Config
}

func NewGoogleClient(conf *oauth2.Config) *GoogleClient {
	return &GoogleClient{conf}
}

func (gc *GoogleClient) AuthURL(state, verifier, nonce string) string {
	url := gc.conf.AuthCodeURL(state, oauth2.S256ChallengeOption(verifier), oauth2.SetAuthURLParam("nonce", nonce))
	return url
}

func (gc *GoogleClient) GetProfile(
	ctx context.Context,
	code, verifier, nonce string,
) (*domain.OAuthProfile, error) {
	token, err := gc.conf.Exchange(ctx, code, oauth2.VerifierOption(verifier))
	if err != nil {
		return &domain.OAuthProfile{}, err
	}

	rawIDToken, ok := token.Extra("id_token").(string)
	if !ok {
		return &domain.OAuthProfile{}, errors.New("id_token not found")
	}

	provider, err := oidc.NewProvider(
		ctx,
		"https://accounts.google.com",
	)
	if err != nil {
		return &domain.OAuthProfile{}, err
	}

	idVerifier := provider.Verifier(&oidc.Config{
		ClientID: gc.conf.ClientID,
	})

	idToken, err := idVerifier.Verify(ctx, rawIDToken)
	if err != nil {
		return &domain.OAuthProfile{}, err
	}

	if nonce == "" || idToken.Nonce != nonce {
		return nil, domain.ErrOAuthStateInvalid
	}

	var profile domain.OAuthProfile

	if err := idToken.Claims(&profile); err != nil {
		return &domain.OAuthProfile{}, err
	}
	profile.Provider = domain.ProviderGoogle
	return &profile, nil
}
