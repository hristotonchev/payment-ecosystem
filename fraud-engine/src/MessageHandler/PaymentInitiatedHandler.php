<?php

namespace App\MessageHandler;

use App\Message\PaymentInitiatedMessage;
use App\Message\PaymentProcessedMessage;
use App\Service\FraudChecker;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class PaymentInitiatedHandler
{
    public function __construct(
        private readonly FraudChecker        $fraudChecker,
        private readonly MessageBusInterface $bus,
        private readonly LoggerInterface     $logger,
    ) {}

    public function __invoke(PaymentInitiatedMessage $message): void
    {
        $this->logger->info('Payment assessment started', [
            'transaction_id' => $message->transactionId,
            'amount'         => $message->amount,
            'correlation_id' => $message->correlationId,
        ]);

        $assessment = $this->fraudChecker->check($message->amount, $message->paymentMethod);

        $this->bus->dispatch(new PaymentProcessedMessage(
            transactionId: $message->transactionId,
            highRisk:      $assessment->isHighRisk,
            reason:        $assessment->reason,
            riskScore:     $assessment->riskScore,
            correlationId: $message->correlationId,
        ));

        $this->logger->info('Payment assessed', [
            'transaction_id' => $message->transactionId,
            'high_risk'      => $assessment->isHighRisk,
            'risk_score'     => $assessment->riskScore,
            'correlation_id' => $message->correlationId,
        ]);
    }
}

