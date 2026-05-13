<?php

namespace App\Tests\Unit\MessageHandler;

use App\Entity\Transaction;
use App\Message\PaymentNotificationMessage;
use App\Message\PaymentProcessedMessage;
use App\MessageHandler\PaymentProcessedHandler;
use App\Repository\TransactionRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class PaymentProcessedHandlerTest extends TestCase
{
    private TransactionRepository&MockObject $transactions;
    private MessageBusInterface&MockObject   $bus;
    private PaymentProcessedHandler          $handler;

    protected function setUp(): void
    {
        $this->transactions = $this->createMock(TransactionRepository::class);
        $this->bus          = $this->createMock(MessageBusInterface::class);

        $this->handler = new PaymentProcessedHandler(
            transactions: $this->transactions,
            bus:          $this->bus,
            logger:       new NullLogger(),
            adminEmail:   'admin@example.com',
        );
    }

    public function test_low_risk_accepts_transaction_and_notifies_customer(): void
    {
        $transaction = $this->buildTransaction();

        $this->transactions->method('findOrFail')->willReturn($transaction);
        $this->transactions->expects($this->once())->method('save');

        $this->bus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(fn($msg) =>
                $msg instanceof PaymentNotificationMessage &&
                $msg->recipientType === 'customer' &&
                $msg->recipientEmail === 'customer@example.com'
            ))
            ->willReturn(new Envelope(new \stdClass()));

        ($this->handler)($this->buildMessage(highRisk: false));
    }

    public function test_high_risk_rejects_transaction_and_notifies_admin(): void
    {
        $transaction = $this->buildTransaction();

        $this->transactions->method('findOrFail')->willReturn($transaction);
        $this->transactions->expects($this->once())->method('save');

        $this->bus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(fn($msg) =>
                $msg instanceof PaymentNotificationMessage &&
                $msg->recipientType === 'admin' &&
                $msg->recipientEmail === 'admin@example.com' &&
                $msg->status === 'high_risk'
            ))
            ->willReturn(new Envelope(new \stdClass()));

        ($this->handler)($this->buildMessage(highRisk: true));

        $this->assertSame(\App\Enum\TransactionStatus::Rejected, $transaction->getStatus());
    }

    public function test_correlation_id_is_propagated_to_notification(): void
    {
        $this->transactions->method('findOrFail')->willReturn($this->buildTransaction());
        $this->transactions->method('save');

        $this->bus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(fn($msg) =>
                $msg instanceof PaymentNotificationMessage &&
                $msg->correlationId === 'test-correlation-123'
            ))
            ->willReturn(new Envelope(new \stdClass()));

        ($this->handler)($this->buildMessage(highRisk: false, correlationId: 'test-correlation-123'));
    }

    private function buildMessage(bool $highRisk, string $correlationId = 'corr-1'): PaymentProcessedMessage
    {
        return new PaymentProcessedMessage(
            transactionId: 'tx-1',
            highRisk:      $highRisk,
            reason:        $highRisk ? 'Amount exceeds threshold' : 'Amount within normal range',
            riskScore:     $highRisk ? 0.8 : 0.0,
            correlationId: $correlationId,
        );
    }

    private function buildTransaction(): Transaction
    {
        return new Transaction('tx-1', 'user-1', '250.00', 'USD', 'credit_card', 'customer@example.com', 'corr-1');
    }
}

