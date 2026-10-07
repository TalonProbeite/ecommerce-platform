FROM golang:1.27-alpine AS builder

RUN apk add --no-cache ca-certificates

WORKDIR /app

COPY auth/go.mod auth/go.sum ./auth/
COPY pkg/shared/go.mod pkg/shared/go.sum ./pkg/shared/

WORKDIR /app/auth

RUN go mod download

COPY auth/ ./
COPY pkg/shared/ ../pkg/shared/

RUN CGO_ENABLED=0 GOOS=linux GOARCH=amd64 \
    go build -ldflags="-s -w" \
    -o /auth-service \
    ./cmd/app/main.go


FROM alpine:latest

COPY --from=builder /etc/ssl/certs/ca-certificates.crt /etc/ssl/certs/

WORKDIR /app

RUN addgroup -S appgroup && \
    adduser -S appuser -G appgroup

USER appuser

COPY --from=builder /auth-service ./auth-service

EXPOSE 8080

CMD ["./auth-service"]
