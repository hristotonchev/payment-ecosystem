<?php

namespace App\Message;

final class PaymentNotificationMessage
{
    public function __construct(
        public readonly string $transactionId,
        public readonly string $recipientEmail,
        public readonly string $recipientType,
        public readonly string $status,
        public readonly float  $amount,
        public readonly string $currency,
        public readonly string $correlationId,
    ) {}
}

