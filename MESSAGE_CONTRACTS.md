// ============================================================================
// Payment Ecosystem — Message Contract Definitions (v1.0)
// ============================================================================
//
// This file documents the canonical message payloads shared across all services.
// Each service implements these as concrete classes in its own codebase, but
// the structure and field semantics MUST remain consistent.
//
// All timestamps are ISO 8601 UTC (RFC 3339).
// All identifiers are UUID v4 (no leading/trailing whitespace).
// All monetary amounts are stored as floats (2 decimal places in practice).
//
// ============================================================================

// ─────────────────────────────────────────────────────────────────────────
// EVENT: payment.initiated
// ─────────────────────────────────────────────────────────────────────────
// Published by: Payment Gateway
// Consumed by: Fraud Engine
// Purpose: Notify the fraud engine of a new payment attempt.
//
// Example:
{
  "event_type": "payment.initiated",
  "event_version": "1.0",
  "correlation_id": "550e8400-e29b-41d4-a716-446655440000",
  "occurred_at": "2025-05-12T14:30:00Z",
  "transaction_id": "f47ac10b-58cc-4372-a567-0e02b2c3d479",
  "user_id": "user-12345",
  "amount": 1500.50,
  "currency": "USD",
  "payment_method": "credit_card"
}

// Field Definitions:
// - event_type (string)        : Always "payment.initiated"
// - event_version (string)      : Always "1.0"
// - correlation_id (uuid)       : Unique identifier for this payment flow across all services
// - occurred_at (iso8601)       : Server timestamp when the event was created
// - transaction_id (uuid)       : Unique identifier for this payment transaction
// - user_id (string)            : Application-level user identifier
// - amount (number)             : Payment amount (positive, up to 2 decimals)
// - currency (string)           : ISO 4217 currency code (e.g. "USD", "EUR")
// - payment_method (string)     : Payment method (e.g. "credit_card", "debit_card", "bank_transfer")


// ─────────────────────────────────────────────────────────────────────────
// EVENT: payment.processed
// ─────────────────────────────────────────────────────────────────────────
// Published by: Fraud Engine
// Consumed by: Payment Gateway
// Purpose: Communicate fraud assessment result back to the payment gateway.
//
// Example:
{
  "event_type": "payment.processed",
  "event_version": "1.0",
  "correlation_id": "550e8400-e29b-41d4-a716-446655440000",
  "occurred_at": "2025-05-12T14:30:01Z",
  "transaction_id": "f47ac10b-58cc-4372-a567-0e02b2c3d479",
  "high_risk": false,
  "reason": "Amount within normal range",
  "risk_score": 0.15
}

// Field Definitions:
// - event_type (string)        : Always "payment.processed"
// - event_version (string)      : Always "1.0"
// - correlation_id (uuid)       : Same as originating payment.initiated
// - occurred_at (iso8601)       : Server timestamp when the event was created
// - transaction_id (uuid)       : Same as originating payment.initiated
// - high_risk (boolean)         : True if payment flagged as high-risk
// - reason (string)             : Human-readable explanation of the assessment result
// - risk_score (number)         : Optional: float 0.0-1.0 indicating confidence (0=low risk, 1=high risk)


// ─────────────────────────────────────────────────────────────────────────
// EVENT: payment.notification
// ─────────────────────────────────────────────────────────────────────────
// Published by: Payment Gateway, Fraud Engine
// Consumed by: Notification Service
// Purpose: Instruct the notification service to send an email about the payment.
//
// Example (Customer Notification):
{
  "event_type": "payment.notification",
  "event_version": "1.0",
  "correlation_id": "550e8400-e29b-41d4-a716-446655440000",
  "occurred_at": "2025-05-12T14:30:02Z",
  "transaction_id": "f47ac10b-58cc-4372-a567-0e02b2c3d479",
  "recipient_email": "john.doe@example.com",
  "recipient_type": "customer",
  "subject": "Payment Confirmation",
  "body": "Your payment of $1500.50 USD has been processed successfully.",
  "status": "accepted"
}

// Example (Admin Notification — High Risk):
{
  "event_type": "payment.notification",
  "event_version": "1.0",
  "correlation_id": "550e8400-e29b-41d4-a716-446655440000",
  "occurred_at": "2025-05-12T14:30:02Z",
  "transaction_id": "f47ac10b-58cc-4372-a567-0e02b2c3d479",
  "recipient_email": "admin@example.com",
  "recipient_type": "admin",
  "subject": "Fraud Alert: High-Risk Payment",
  "body": "A high-risk payment of $5000.00 USD from user-12345 requires review.",
  "status": "pending_review"
}

// Field Definitions:
// - event_type (string)        : Always "payment.notification"
// - event_version (string)      : Always "1.0"
// - correlation_id (uuid)       : Same as originating payment transaction
// - occurred_at (iso8601)       : Server timestamp when the event was created
// - transaction_id (uuid)       : Same as originating payment.initiated
// - recipient_email (string)    : Email address to send the notification to
// - recipient_type (string)     : "customer" | "admin" | "merchant" (determines template/context)
// - subject (string)            : Email subject line
// - body (string)               : Email body text (plain text or HTML depending on implementation)
// - status (string)             : Transaction status context ("pending", "accepted", "pending_review", "rejected")


// ============================================================================
// IMPLEMENTATION NOTES
// ============================================================================

// 1. JSON Serialization
//    - Use camelCase for JSON field names (as shown above).
//    - All objects must serialize to valid JSON without custom encoders.

// 2. Correlation ID
//    - MUST be UUID v4 (not Ulid, Uuid1, or any other format).
//    - Generated by Payment Gateway on first HTTP request.
//    - Passed through all downstream events without modification.
//    - Logged by every service with every message.

// 3. Timestamps
//    - MUST be ISO 8601 UTC (RFC 3339 format: "2025-05-12T14:30:00Z").
//    - Generated at event creation time by the publishing service.
//    - Never modified during transit.

// 4. Idempotency
//    - A service MAY receive the same event twice on failure/retry.
//    - Implementations MUST be idempotent (safe to re-process).
//    - Fraud Engine: Store transaction_id in DB; skip if already processed.
//    - Notification Service: Email provider or local DB tracks sent notification IDs.

// 5. Versioning
//    - Current schema version is "1.0".
//    - If breaking changes required, create "2.0" schema.
//    - Services MAY support multiple versions during transition periods.
//    - Fall back gracefully on unknown event_version.

// 6. Error Handling
//    - If a service cannot parse an event, log it and move to dead-letter queue.
//    - Do NOT retry parsing errors automatically; flag for manual review.
//    - Network/transient errors (Messenger timeout) trigger retry logic.

// ============================================================================
// EXAMPLES: Publishing & Consuming
// ============================================================================

// ─── Payment Gateway: Publishing payment.initiated ───
//
// In PaymentController.php:
//   $transaction = $service->initiate($user_id, $amount, $currency, $payment_method);
//   $message = new PaymentInitiatedMessage(
//       transaction_id: $transaction->getId(),
//       user_id: $user_id,
//       amount: $amount,
//       currency: $currency,
//       payment_method: $payment_method,
//       correlation_id: $request->attributes->get('correlation_id')
//   );
//   $messageBus->dispatch($message);


// ─── Fraud Engine: Consuming payment.initiated, Publishing payment.processed ───
//
// In PaymentInitiatedHandler.php:
//   #[AsMessageHandler]
//   public function __invoke(PaymentInitiatedMessage $message) {
//       $riskAssessment = $this->fraudChecker->check(
//           $message->amount,
//           $message->payment_method
//       );
//       $processed = new PaymentProcessedMessage(
//           transaction_id: $message->transaction_id,
//           correlation_id: $message->correlation_id,
//           high_risk: $riskAssessment->isHighRisk(),
//           reason: $riskAssessment->reason()
//       );
//       $this->messageBus->dispatch($processed);
//   }


// ─── Notification Service: Consuming payment.notification ───
//
// In PaymentNotificationHandler.php:
//   #[AsMessageHandler]
//   public function __invoke(PaymentNotificationMessage $message) {
//       $email = (new Email())
//           ->to($message->recipient_email)
//           ->subject($message->subject)
//           ->text($message->body);
//       $this->mailer->send($email);
//   }


