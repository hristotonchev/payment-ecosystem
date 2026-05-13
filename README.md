# Payment Ecosystem — Microservices Payment Processing Platform

A robust, event-driven microservices architecture for payment processing with asynchronous message flow, fraud detection, and email notifications.

---

## 🚀 Architecture Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                        Client / External API                     │
└──────────────────────────┬──────────────────────────────────────┘
                           │
                           ▼
        ┌──────────────────────────────────┐
        │  Payment Gateway (Service A)     │
        │  - HTTP Entry Point              │
        │  - Transaction Persistence       │
        │  - Status Management             │
        └──┬───────────────────────────────┘
           │
           │ Publishes: payment.initiated
           │           payment.notification
           │ Consumes:  payment.processed
           ▼
        ┌──────────────────────────────────┐
        │       RabbitMQ (Message Bus)     │
        │  - payment.initiated             │
        │  - payment.processed             │
        │  - payment.notification          │
        └──┬─────────────────────┬─────────┘
           │                     │
    ┌──────▼─────────┐    ┌──────▼──────────────────┐
    │ Fraud Engine   │    │ Notification Service   │
    │ (Service B)    │    │ (Service C)            │
    │ - Risk Check   │    │ - Email Dispatch       │
    │ - Assessment   │    │ - Mailpit Integration  │
    └────────────────┘    └───────────────────────┘
```

### Services

| Service | Role | Tech | Ports |
|---------|------|------|-------|
| **Payment Gateway** | HTTP API + Transaction Manager | Symfony 7, MySQL | 8001 → 8000 |
| **Fraud Engine** | Risk Assessment Logic | Symfony 7 | Internal only |
| **Notification Service** | Email Delivery | Symfony 7, Mailpit | Internal only |

### Infrastructure

| Service | Ports |
|---------|-------|
| MySQL | 3306 |
| RabbitMQ | 5672 (AMQP), 15672 (Management UI) |
| Mailpit | 8025 (Web UI), 1025 (SMTP) |

---

## 📦 Message Contracts

All services use **versioned, structured events** with correlation tracking.

### v1 Events

#### `payment.initiated`
Published by: Payment Gateway  
Consumed by: Fraud Engine

```json
{
  "event_type": "payment.initiated",
  "event_version": "1.0",
  "correlation_id": "uuid",
  "occurred_at": "2025-05-12T14:30:00Z",
  "transaction_id": "uuid",
  "user_id": "string",
  "amount": 9999.99,
  "currency": "USD",
  "payment_method": "credit_card"
}
```

#### `payment.processed`
Published by: Fraud Engine  
Consumed by: Payment Gateway

```json
{
  "event_type": "payment.processed",
  "event_version": "1.0",
  "correlation_id": "uuid",
  "occurred_at": "2025-05-12T14:30:01Z",
  "transaction_id": "uuid",
  "high_risk": false,
  "reason": "Amount within normal range"
}
```

#### `payment.notification`
Published by: Payment Gateway (on confirm/high-risk) & Fraud Engine (error path)  
Consumed by: Notification Service

```json
{
  "event_type": "payment.notification",
  "event_version": "1.0",
  "correlation_id": "uuid",
  "occurred_at": "2025-05-12T14:30:02Z",
  "transaction_id": "uuid",
  "recipient_email": "customer@example.com",
  "recipient_type": "customer",
  "subject": "Payment Confirmation",
  "body": "Your payment has been processed successfully."
}
```

---

## 🏗️ Architecture Decisions

### 1. **Asynchronous-First Design**
- All inter-service communication via RabbitMQ events.
- No REST calls between services (except in test/debug scenarios).
- Decouples services: each can be deployed, scaled, and recovered independently.

### 2. **Correlation ID Propagation**
- Generated at the HTTP entry point (Payment Gateway).
- Passed through all events and logged in every service.
- Enables end-to-end tracing and audit trails without external APM.

### 3. **Exponential Backoff Retry Strategy**
- Symfony Messenger configured with 3 retry attempts.
- Failed messages moved to a failure transport for manual inspection/replay.
- No infinite retry loops.

### 4. **Database per Service**
- Payment Gateway: MySQL (transaction storage required).
- Fraud Engine: No database (stateless risk checks).
- Notification Service: No database (idempotent delivery via event replay).

### 5. **No Shared Code**
- Each service has its own `src/`, `config/`, `tests/`.
- Cross-service contracts defined in this README (message schemas).
- Prevents hidden coupling and version conflicts.

### 6. **Email Testing with Mailpit**
- Dev/test environment uses in-memory SMTP mock.
- No external email provider required for local runs.
- Web UI available at `http://localhost:8025` to inspect sent emails.

---

## 🎯 Running the Project

### Prerequisites
- Docker & Docker Compose (v2+)
- macOS, Linux, or WSL2

### Startup

```bash
# Build images
make build

# Start all services
make up

# Wait for services to stabilize (~10s)
sleep 10

# Run migrations (Payment Gateway database schema)
make migrate
```

Services will be available at:
- **Payment Gateway HTTP API**: `http://localhost:8001`
- **RabbitMQ Management**: `http://localhost:15672` (guest/guest)
- **Mailpit Web UI**: `http://localhost:8025`

### Verify Health

```bash
# View all logs
make logs

# Shell into a service for manual testing
make shell-gateway
make shell-fraud
make shell-notify
```

### Shutdown

```bash
make down
```

---

## 🧪 Running Tests

Each service has independent test suites. Run all or individually:

```bash
# All tests (runs sequentially: gateway → fraud → notify)
make test

# Individual service tests
make test-gateway      # Unit + Integration (includes DB)
make test-fraud        # Unit only
make test-notify       # Unit only
```

### Test Coverage by Service

**Payment Gateway** (`payment-gateway/tests/`)
- `Unit/Service/PaymentServiceTest.php` — transaction creation, UUID generation, status logic
- `Unit/MessageHandler/PaymentProcessedHandlerTest.php` — high_risk branching, correct recipients
- `Integration/Controller/PaymentControllerTest.php` — full HTTP + DB + dispatch cycle

**Fraud Engine** (`fraud-engine/tests/`)
- `Unit/Service/FraudCheckerTest.php` — amount threshold, boundary cases, risk flags
- `Unit/MessageHandler/PaymentInitiatedHandlerTest.php` — message in → payment.processed out

**Notification Service** (`notification-service/tests/`)
- `Unit/MessageHandler/PaymentNotificationHandlerTest.php` — correct recipient/template selection
- `Integration/Mailer/MailerIntegrationTest.php` — email actually queued via Symfony Mailer

---

## 🔄 Example: Full Payment Flow

### 1. Initiate Payment
```bash
curl -X POST http://localhost:8001/payments/initiate \
  -H "Content-Type: application/json" \
  -d '{
    "user_id": "user-123",
    "amount": 50.00,
    "currency": "USD",
    "payment_method": "credit_card"
  }'

# Response example:
# {
#   "transaction_id": "550e8400-e29b-41d4-a716-446655440000",
#   "status": "pending"
# }
```

### 2. Behind the Scenes
- Payment Gateway stores transaction with `status=pending`.
- Publishes `payment.initiated` with `correlation_id`.
- Fraud Engine consumes, checks amount, publishes `payment.processed`.
- Payment Gateway consumes; if not high_risk, publishes `payment.notification` with customer email.
- Notification Service consumes and queues email via Mailpit.

### 3. Confirm Payment (Manual Step)
```bash
curl -X POST http://localhost:8001/payments/{transaction_id}/confirm \
  -H "Content-Type: application/json"

# Updates transaction to status=accepted, publishes another notification for confirmation.
```

### 4. Check Email
Visit `http://localhost:8025` → no external email required, all captured locally.

---

## 📝 Environment Variables

### Payment Gateway (`.env`)
```dotenv
APP_ENV=prod
APP_SECRET=change-me-in-production
DATABASE_URL="mysql://payments:payments@mysql:3306/payments?serverVersion=8.0&charset=utf8mb4"
RABBITMQ_DSN="amqp://guest:guest@rabbitmq:5672/%2f"
ADMIN_EMAIL="admin@example.com"
```

### Fraud Engine (`.env`)
```dotenv
APP_ENV=prod
APP_SECRET=change-me-in-production
RABBITMQ_DSN="amqp://guest:guest@rabbitmq:5672/%2f"
```

### Notification Service (`.env`)
```dotenv
APP_ENV=prod
APP_SECRET=change-me-in-production
RABBITMQ_DSN="amqp://guest:guest@rabbitmq:5672/%2f"
MAILER_DSN="smtp://mailpit:1025"
MAILER_FROM="noreply@payment-ecosystem.dev"
```

---

## 🛠️ CI/CD with GitHub Actions

On every push/PR to `main`:

1. **Payment Gateway Job**
   - Composer install, migrations, phpunit (unit + integration with MySQL)
   
2. **Fraud Engine Job**
   - Composer install, phpunit (unit only)
   
3. **Notification Service Job**
   - Composer install, phpunit (unit only)

Jobs run in parallel; each service fails independently. See `.github/workflows/ci.yml` for full config.

---

## 🚦 Status Codes & Error Handling

### HTTP Responses (Payment Gateway)

| Endpoint | Method | Status | Response |
|----------|--------|--------|----------|
| `/payments/initiate` | POST | 201 | `{"transaction_id": "...", "status": "pending"}` |
| `/payments/initiate` | POST | 400 | `{"error": "Invalid amount"}` |
| `/payments/{id}/confirm` | POST | 200 | `{"transaction_id": "...", "status": "accepted"}` |
| `/payments/{id}/confirm` | POST | 404 | `{"error": "Transaction not found"}` |

### Message Failures (Retries)

- Queued message fails: Messenger retries 3 times with exponential backoff (1s, 2s, 5s).
- After 3 failures: event moved to `failed_transport` for manual inspection.
- Via CLI: `php bin/console messenger:transport` commands to list/replay failed messages.

---

## 📊 Observability & Correlation IDs

Every request/event includes a `correlation_id`:

1. Client sends HTTP request (no header needed; generated server-side).
2. Payment Gateway generates `correlation_id` (UUID v4) and logs it.
3. All published events carry the same `correlation_id`.
4. All consuming services log `correlation_id` with every message.
5. Logs can be filtered/aggregated by `correlation_id` for end-to-end insight.

Example log output:
```
[2025-05-12T14:30:00Z] correlation_id=550e8400-e29b-41d4-a716-446655440000 message="Payment initiated" transaction_id=...
[2025-05-12T14:30:01Z] correlation_id=550e8400-e29b-41d4-a716-446655440000 message="Payment assessed" risk_level=low
[2025-05-12T14:30:02Z] correlation_id=550e8400-e29b-41d4-a716-446655440000 message="Email queued" recipient=customer@example.com
```

---

## 🐛 Troubleshooting

### Services won't start
```bash
# Check Docker images built correctly
docker compose --project-name payment-ecosystem build --no-cache

# Inspect logs
make logs | grep -i error
```

### RabbitMQ / MySQL unhealthy
```bash
# Reset volumes
docker compose down -v
make up
```

### Messages not consumed
```bash
# Check failed messages transport
docker compose exec payment-gateway \
  php bin/console messenger:transport | grep failed

# Replay a failed message
docker compose exec payment-gateway \
  php bin/console messenger:transport --recovery-option=failed_transport
```

### Email not sent
```bash
# Visit Mailpit UI: http://localhost:8025
# Check Notification Service logs
docker compose logs -f notification-service | grep -i mail
```

---

## 📚 TODO / Future Improvements

- [ ] **OAuth2 / API Key Authentication** on Payment Gateway endpoints
- [ ] **Idempotency Key Support** to prevent duplicate payments (with request deduplication)
- [ ] **Circuit Breaker Pattern** using Symfony HTTP Client resilience layer
- [ ] **Secrets Management** (HashiCorp Vault / AWS Secrets Manager) for production
- [ ] **Event Sourcing** for complete transaction audit trail (EventStoreDB)
- [ ] **Rate Limiting** on payment initiation endpoint (token bucket / sliding window)
- [ ] **Real Fraud Provider** integration (Stripe Radar, Sift, etc.)
- [ ] **Distributed Tracing** with OpenTelemetry + Jaeger UI
- [ ] **GraphQL Query API** for transaction analytics
- [ ] **Webhook Callbacks** to notify external systems of payment status changes
- [ ] **Multi-currency Support** with real-time exchange rates
- [ ] **Payment Method Diversification** (PayPal, Apple Pay, crypto)
- [ ] **Compliance & KYC** integration
- [ ] **Load Testing** with k6 or Locust

---

