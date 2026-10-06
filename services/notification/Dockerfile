FROM golang:1.27-alpine AS builder

RUN apk add --no-cache ca-certificates

WORKDIR /app

COPY notification/go.mod notification/go.sum ./notification/
COPY pkg/shared/go.mod pkg/shared/go.sum ./pkg/shared/

WORKDIR /app/notification

RUN go mod download

COPY notification/ ./
COPY pkg/shared/ ../pkg/shared/

RUN CGO_ENABLED=0 GOOS=linux GOARCH=amd64 \
    go build -ldflags="-s -w" \
    -o notification-service \
    ./cmd/app/main.go

FROM alpine:latest

COPY --from=builder /etc/ssl/certs/ca-certificates.crt /etc/ssl/certs/

WORKDIR /app

RUN addgroup -S appgroup && \
    adduser -S appuser -G appgroup

USER appuser

COPY --from=builder /app/notification/notification-service .
COPY --from=builder /app/notification/templates ./templates

EXPOSE 8080

CMD ["./notification-service"]
