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

func (gc *GoogleClient) AuthURL(state string) string {
	url := gc.conf.AuthCodeURL(state)
	return url
}

func (gc *GoogleClient) GetProfile(
	ctx context.Context,
	code string,
) (*domain.GoogleProfile, error) {
	token, err := gc.conf.Exchange(ctx, code)
	if err != nil {
		return &domain.GoogleProfile{}, err
	}

	rawIDToken, ok := token.Extra("id_token").(string)
	if !ok {
		return &domain.GoogleProfile{}, errors.New("id_token not found")
	}

	provider, err := oidc.NewProvider(
		ctx,
		"https://accounts.google.com",
	)
	if err != nil {
		return &domain.GoogleProfile{}, err
	}

	verifier := provider.Verifier(&oidc.Config{
		ClientID: gc.conf.ClientID,
	})

	idToken, err := verifier.Verify(ctx, rawIDToken)
	if err != nil {
		return &domain.GoogleProfile{}, err
	}

	var profile domain.GoogleProfile

	if err := idToken.Claims(&profile); err != nil {
		return &domain.GoogleProfile{}, err
	}

	return &profile, nil
}
