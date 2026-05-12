<?php

namespace App\MessageHandler;

use App\Message\PaymentNotificationMessage;
use App\Message\PaymentProcessedMessage;
use App\Repository\TransactionRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class PaymentProcessedHandler
{
    public function __construct(
        private readonly TransactionRepository $transactions,
        private readonly MessageBusInterface   $bus,
        private readonly LoggerInterface       $logger,
        private readonly string                $adminEmail,
    ) {}

    public function __invoke(PaymentProcessedMessage $message): void
    {
        $this->logger->info('Payment processed received', [
            'transaction_id' => $message->transactionId,
            'high_risk'      => $message->highRisk,
            'risk_score'     => $message->riskScore,
            'reason'         => $message->reason,
            'correlation_id' => $message->correlationId,
        ]);

        $transaction = $this->transactions->findOrFail($message->transactionId);

        if ($message->highRisk) {
            $this->bus->dispatch(new PaymentNotificationMessage(
                transactionId:  $message->transactionId,
                recipientEmail: $this->adminEmail,
                recipientType:  'admin',
                status:         'high_risk',
                amount:         (float) $transaction->getAmount(),
                currency:       $transaction->getCurrency(),
                correlationId:  $message->correlationId,
            ));

            $this->logger->warning('High-risk payment flagged, admin notified', [
                'transaction_id' => $message->transactionId,
                'correlation_id' => $message->correlationId,
            ]);

            return;
        }

        $transaction->accept();
        $this->transactions->save($transaction);

        $this->bus->dispatch(new PaymentNotificationMessage(
            transactionId:  $message->transactionId,
            recipientEmail: $transaction->getCustomerEmail(),
            recipientType:  'customer',
            status:         $transaction->getStatus()->value,
            amount:         (float) $transaction->getAmount(),
            currency:       $transaction->getCurrency(),
            correlationId:  $message->correlationId,
        ));

        $this->logger->info('Transaction accepted, customer notified', [
            'transaction_id' => $message->transactionId,
            'correlation_id' => $message->correlationId,
        ]);
    }
}
