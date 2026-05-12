<?php

namespace App\Message;

final class PaymentInitiatedMessage
{
    public function __construct(
        public readonly string $transactionId,
        public readonly string $userId,
        public readonly float  $amount,
        public readonly string $currency,
        public readonly string $paymentMethod,
        public readonly string $correlationId,
    ) {}
}

