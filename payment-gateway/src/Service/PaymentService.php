<?php

namespace App\Service;

use App\Entity\Transaction;
use App\Message\PaymentInitiatedMessage;
use App\Message\PaymentNotificationMessage;
use App\Repository\TransactionRepository;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

class PaymentService
{
    public function __construct(
        private readonly TransactionRepository $transactions,
        private readonly MessageBusInterface   $bus,
    ) {}

    public function initiate(
        string $userId,
        float  $amount,
        string $currency,
        string $paymentMethod,
        string $customerEmail,
    ): Transaction {
        $transactionId = Uuid::v4()->toRfc4122();
        $correlationId = Uuid::v4()->toRfc4122();

        $transaction = new Transaction(
            id:            $transactionId,
            userId:        $userId,
            amount:        (string) $amount,
            currency:      strtoupper($currency),
            paymentMethod: $paymentMethod,
            customerEmail: $customerEmail,
            correlationId: $correlationId,
        );

        $this->transactions->save($transaction);

        $this->bus->dispatch(new PaymentInitiatedMessage(
            transactionId: $transactionId,
            userId:        $userId,
            amount:        $amount,
            currency:      strtoupper($currency),
            paymentMethod: $paymentMethod,
            customerEmail: $customerEmail,
            correlationId: $correlationId,
        ));

        return $transaction;
    }

    public function confirm(string $transactionId): Transaction
    {
        $transaction = $this->transactions->findOrFail($transactionId);
        $transaction->accept();
        $this->transactions->save($transaction);

        $this->bus->dispatch(new PaymentNotificationMessage(
            transactionId:  $transactionId,
            recipientEmail: $transaction->getCustomerEmail(),
            recipientType:  'customer',
            status:         $transaction->getStatus()->value,
            amount:         (float) $transaction->getAmount(),
            currency:       $transaction->getCurrency(),
            correlationId:  $transaction->getCorrelationId(),
        ));

        return $transaction;
    }
}
