<?php

namespace App\Tests\Unit\Service;

use App\Entity\Transaction;
use App\Enum\TransactionStatus;
use App\Message\PaymentInitiatedMessage;
use App\Repository\TransactionRepository;
use App\Service\PaymentService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class PaymentServiceTest extends TestCase
{
    private TransactionRepository&MockObject $transactions;
    private MessageBusInterface&MockObject   $bus;
    private PaymentService                   $service;

    protected function setUp(): void
    {
        $this->transactions = $this->createMock(TransactionRepository::class);
        $this->bus          = $this->createMock(MessageBusInterface::class);
        $this->service      = new PaymentService($this->transactions, $this->bus);
    }

    public function testInitiateCreatesTransactionWithPendingStatus(): void
    {
        $this->transactions->expects($this->once())->method('save');
        $this->bus->method('dispatch')->willReturn(new Envelope(new \stdClass()));

        $transaction = $this->service->initiate('user-1', 500.0, 'USD', 'credit_card', 'c@example.com');

        $this->assertNotEmpty($transaction->getId());
        $this->assertSame(TransactionStatus::Pending, $transaction->getStatus());
    }

    public function testInitiateDispatchesPaymentInitiatedMessage(): void
    {
        $this->transactions->method('save');
        $this->bus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(PaymentInitiatedMessage::class))
            ->willReturn(new Envelope(new \stdClass()));

        $this->service->initiate('user-1', 500.0, 'USD', 'credit_card', 'c@example.com');
    }

    public function testInitiateNormalizesCurrencyToUppercase(): void
    {
        $this->transactions->method('save');
        $this->bus->method('dispatch')->willReturn(new Envelope(new \stdClass()));

        $transaction = $this->service->initiate('user-1', 100.0, 'eur', 'card', 'c@example.com');

        $this->assertSame('EUR', $transaction->getCurrency());
    }

    public function testInitiateGeneratesUniqueCorrelationId(): void
    {
        $this->transactions->method('save');
        $this->bus->method('dispatch')->willReturn(new Envelope(new \stdClass()));

        $t1 = $this->service->initiate('user-1', 100.0, 'USD', 'card', 'a@example.com');
        $t2 = $this->service->initiate('user-2', 200.0, 'USD', 'card', 'b@example.com');

        $this->assertNotSame($t1->getCorrelationId(), $t2->getCorrelationId());
    }

    public function testConfirmAcceptsTransactionAndNotifiesCustomer(): void
    {
        $transaction = $this->buildTransaction();

        $this->transactions->method('findOrFail')->willReturn($transaction);
        $this->transactions->expects($this->once())->method('save');
        $this->bus->expects($this->once())->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $result = $this->service->confirm($transaction->getId());

        $this->assertSame(TransactionStatus::Accepted, $result->getStatus());
    }

    private function buildTransaction(): Transaction
    {
        return new Transaction('tx-1', 'user-1', '250.00', 'USD', 'credit_card', 'c@example.com', 'corr-1');
    }
}
