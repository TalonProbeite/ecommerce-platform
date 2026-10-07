//go:build integration

package integration

import (
	"context"
	"errors"
	"fmt"
	"os"
	"os/signal"
	"path/filepath"
	"testing"
	"time"

	"github.com/testcontainers/testcontainers-go"
	tcmongo "github.com/testcontainers/testcontainers-go/modules/mongodb"
	tcrabbit "github.com/testcontainers/testcontainers-go/modules/rabbitmq"
	"github.com/testcontainers/testcontainers-go/wait"
)

type environment struct {
	mongoURI   string
	amqpURL    string
	smtpHost   string
	smtpPort   string
	mailhogAPI string
	mailhogUI  string
}

var env environment

func TestMain(m *testing.M) {
	os.Exit(run(m))
}

func run(m *testing.M) int {
	ctx := context.Background()

	root, err := moduleRoot()
	if err != nil {
		fmt.Fprintln(os.Stderr, "find module root:", err)

		return 1
	}

	if err := os.Chdir(root); err != nil {
		fmt.Fprintln(os.Stderr, "chdir to module root:", err)

		return 1
	}

	var containers []testcontainers.Container

	defer func() {
		for _, c := range containers {
			if err := testcontainers.TerminateContainer(c); err != nil {
				fmt.Fprintln(os.Stderr, "terminate container:", err)
			}
		}
	}()

	mongoCtr, err := tcmongo.Run(ctx, "mongo:7")
	if mongoCtr != nil {
		containers = append(containers, mongoCtr)
	}
	if err != nil {
		fmt.Fprintln(os.Stderr, "start mongo:", err)

		return 1
	}

	if env.mongoURI, err = mongoCtr.ConnectionString(ctx); err != nil {
		fmt.Fprintln(os.Stderr, "mongo connection string:", err)

		return 1
	}

	rabbitCtr, err := tcrabbit.Run(ctx, "rabbitmq:3.13-management-alpine")
	if rabbitCtr != nil {
		containers = append(containers, rabbitCtr)
	}
	if err != nil {
		fmt.Fprintln(os.Stderr, "start rabbitmq:", err)

		return 1
	}

	if env.amqpURL, err = rabbitCtr.AmqpURL(ctx); err != nil {
		fmt.Fprintln(os.Stderr, "rabbitmq amqp url:", err)

		return 1
	}

	mailhogCtr, err := testcontainers.GenericContainer(ctx, testcontainers.GenericContainerRequest{
		ContainerRequest: testcontainers.ContainerRequest{
			Image:        "mailhog/mailhog:v1.0.1",
			ExposedPorts: []string{"1025/tcp", "8025/tcp"},
			WaitingFor: wait.ForAll(
				wait.ForListeningPort("1025/tcp"),
				wait.ForListeningPort("8025/tcp"),
			),
		},
		Started: true,
	})
	if mailhogCtr != nil {
		containers = append(containers, mailhogCtr)
	}
	if err != nil {
		fmt.Fprintln(os.Stderr, "start mailhog:", err)

		return 1
	}

	host, err := mailhogCtr.Host(ctx)
	if err != nil {
		fmt.Fprintln(os.Stderr, "mailhog host:", err)

		return 1
	}

	smtpPort, err := mailhogCtr.MappedPort(ctx, "1025/tcp")
	if err != nil {
		fmt.Fprintln(os.Stderr, "mailhog smtp port:", err)

		return 1
	}

	uiPort, err := mailhogCtr.MappedPort(ctx, "8025/tcp")
	if err != nil {
		fmt.Fprintln(os.Stderr, "mailhog ui port:", err)

		return 1
	}

	env.smtpHost = host
	env.smtpPort = smtpPort.Port()
	env.mailhogAPI = fmt.Sprintf("http://%s:%s", host, uiPort.Port())
	env.mailhogUI = env.mailhogAPI

	fmt.Printf("MailHog UI: %s\n", env.mailhogUI)

	code := m.Run()

	holdForInspection()

	return code
}

func holdForInspection() {
	raw := os.Getenv("KEEP_MAILHOG")
	if raw == "" {
		return
	}

	keepFor, err := time.ParseDuration(raw)
	if err != nil || keepFor <= 0 {
		keepFor = 5 * time.Minute
	}

	fmt.Printf("containers stay alive for %s, MailHog UI: %s (Ctrl+C to stop)\n", keepFor, env.mailhogUI)

	ctx, stop := signal.NotifyContext(context.Background(), os.Interrupt)
	defer stop()

	select {
	case <-ctx.Done():
	case <-time.After(keepFor):
	}
}

func moduleRoot() (string, error) {
	dir, err := os.Getwd()
	if err != nil {
		return "", err
	}

	for {
		if _, err := os.Stat(filepath.Join(dir, "go.mod")); err == nil {
			return dir, nil
		}

		parent := filepath.Dir(dir)
		if parent == dir {
			return "", errors.New("go.mod not found")
		}

		dir = parent
	}
}
