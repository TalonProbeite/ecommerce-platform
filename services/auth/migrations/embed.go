// Package migrations embeds SQL migration files for database initialization.
package migrations

import "embed"

// FS embeds SQL migration files.
//
//go:embed *.sql
var FS embed.FS
