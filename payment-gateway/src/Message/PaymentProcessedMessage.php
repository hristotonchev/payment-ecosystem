<?php

namespace App\Message;

final class PaymentProcessedMessage
{
    public function __construct(
        public readonly string $transactionId,
        public readonly bool   $highRisk,
        public readonly string $reason,
        public readonly float  $riskScore,
        public readonly string $correlationId,
    ) {}
}
