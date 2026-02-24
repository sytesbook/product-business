# ---- Builder ----
# Compiles the Go binary with full build toolchain.
FROM golang:1.26-alpine AS builder

WORKDIR /build

# Download dependencies first (layer-cached separately from source).
COPY go.mod ./
RUN go mod download

# Copy source and compile a statically-linked binary.
COPY . .
RUN CGO_ENABLED=0 GOOS=linux go build -o /router-service .

# ---- Production ----
# Minimal runtime image — only the binary and CA certificates.
FROM alpine:3.21 AS production

RUN apk --no-cache add ca-certificates

WORKDIR /app
COPY --from=builder /router-service .

EXPOSE 80
CMD ["./router-service"]

# ---- Development ----
# Mounts source as a volume and recompiles on each container start.
# Restart the container to pick up code changes: docker compose restart router-service
FROM golang:1.26 AS development

WORKDIR /app

EXPOSE 80
CMD ["go", "run", "."]
