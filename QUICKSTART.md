# Quick Start Guide

## Current State (May 12, 2026)

**✅ Complete:**
- Service infrastructure (Dockerfiles, configs, messaging)
- Message contracts and architecture documentation
- Skeleton code for all three microservices
- Correlation ID and retry strategy setup
- CI/CD workflow configuration

**🟡 Next Steps:**
- Build Docker images and start containers
- Install Composer dependencies in each service
- Run database migrations (Payment Gateway)
- Execute tests
- Validate end-to-end message flow

---

## 1. Build & Start Services

```bash
cd /Users/hristotonchev/Workspace/payment-ecosystem

# Build Docker images (~5-10 mins, downloads PHP base)
make build

# Start all services
make up

# Wait for services to stabilize
sleep 10

# Check logs for errors
make logs
```

**Expected output:**
- Payment Gateway: PHP-FPM + Nginx + Messenger worker ready
- Fraud Engine: Messenger worker ready
- Notification Service: Messenger worker ready
- MySQL: healthcheck passed
- RabbitMQ: management UI accessible

---

## 2. Run Database Migrations (Payment Gateway Only)

```bash
# Create transaction schema
make migrate

# Verify:
docker compose exec mysql mysql -uroot -proot payments -e "SHOW TABLES;"
# Output should show: transactions table
```

---

## 3. Run Tests

```bash
# All tests (sequential)
make test

# Individual service tests:
make test-gateway      # Unit + Integration
make test-fraud        # Unit only
make test-notify       # Unit only
```

**Expected:** All tests pass (placeholders + real tests)

---

## 4. Access Services

### Payment Gateway HTTP API
```bash
curl -X POST http://localhost:8001/api/payments/initiate \
  -H "Content-Type: application/json" \
  -d '{
    "user_id": "user-123",
    "amount": 50.00,
    "currency": "USD",
    "payment_method": "credit_card",
    "customer_email": "customer@example.com"
  }'

# Response:
# {
#   "transaction_id": "550e8400-e29b-41d4-a716-446655440000",
#   "status": "pending",
#   "correlation_id": "..."
# }
```

### RabbitMQ Management UI
```
http://localhost:15672
Username: guest
Password: guest
```

### Mailpit (Email Testing)
```
http://localhost:8025
```

---

## 5. Monitor Message Flow

```bash
# Watch logs for correlation ID
make logs | grep correlation_id

# Expected sequence:
# 1. [payment-gateway] correlation_id=xxx message="Payment initiated"
# 2. [fraud-engine] correlation_id=xxx message="Payment assessed"
# 3. [payment-gateway] correlation_id=xxx message="Transaction accepted"
# 4. [notification-service] correlation_id=xxx message="Notification sent"
```

---

## 6. Inspect Failed Messages

If a message fails to process after 3 retries:

```bash
# List failed messages
docker compose exec payment-gateway \
  php bin/console messenger:transport

# Manually replay a failed message (if needed)
docker compose exec payment-gateway \
  php bin/console messenger:transport --recovery-option=failed_transport
```

---

## 7. Stop & Cleanup

```bash
# Stop services (keep data)
make down

# Stop and remove volumes (full reset)
docker compose down -v
```

---

## Common Issues

### Services fail to start
```bash
# Check logs
make logs

# Rebuild from scratch
make rebuild
```

### Composer/pecl installation fails in Docker
```bash
# Clear Docker build cache
docker system prune -a

# Rebuild
make build
```

### RabbitMQ connection refused
```bash
# Wait longer for RabbitMQ healthcheck
sleep 15
docker compose exec payment-gateway \
  php bin/console messenger:transport
```

### MySQL migrations fail
```bash
# Ensure MySQL is healthy
docker compose exec mysql mysqladmin ping -h localhost -u root -proot

# Check database exists
docker compose exec mysql mysql -uroot -proot -e "SHOW DATABASES;"

# Retry migration
make migrate
```

---

## Next Development Steps

1. **Add more tests** in `tests/Unit/` and `tests/Integration/`
   - Mock message handlers
   - Test edge cases (high_risk logic)
   - Test error handling

2. **Add correlation ID middleware** (optional)
   - Allow HTTP clients to pass `X-Correlation-ID` header
   - Generate if not provided
   - Inject into all logs

3. **Add API documentation**
   - OpenAPI/Swagger schema
   - Example requests per endpoint

4. **Add monitoring**
   - Prometheus metrics exporter
   - Grafana dashboards (optional)

5. **Add production hardening**
   - Secrets management (Vault/AWS Secrets Manager)
   - Rate limiting
   - API authentication (OAuth2, API keys)
   - Request validation schemas

---

## Project Structure Reminder

```
payment-ecosystem/
├── README.md                    # Architecture & setup
├── MESSAGE_CONTRACTS.md         # Event schemas
├── INFRASTRUCTURE.md            # Technical decisions
├── docker-compose.yml           # Service orchestration
├── Makefile                     # CLI commands
│
├── payment-gateway/             # HTTP entry point + Transaction manager
│   ├── src/Controller/          # REST endpoints
│   ├── src/Entity/              # Database models
│   ├── src/Service/             # Business logic
│   ├── src/MessageHandler/      # Event consumers
│   └── migrations/              # DB schema
│
├── fraud-engine/                # Risk assessment  (Stateless)
│   ├── src/Service/             # FraudChecker logic
│   ├── src/MessageHandler/      # Event consumer/publisher
│
├── notification-service/        # Email dispatch
│   ├── src/Service/             # NotificationComposer
│   ├── src/MessageHandler/      # Event consumer + Mail sender
│
└── .github/workflows/           # CI/CD (GitHub Actions)
    └── ci.yml                   # Run tests on push
```

---

## Support

- **Architecture Questions**: See `README.md` and `INFRASTRUCTURE.md`
- **Event Contracts**: See `MESSAGE_CONTRACTS.md`
- **Logs & Debugging**: `make logs | grep <service-name>`
- **Manual Testing**: Use `make shell-<service>` to enter a container

---

**Last Updated**: May 12, 2026  
**Status**: Ready for development & testing

