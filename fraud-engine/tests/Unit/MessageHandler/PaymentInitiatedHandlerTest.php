<?php

namespace App\Tests\Unit\MessageHandler;

use App\Message\PaymentInitiatedMessage;
use App\Message\PaymentProcessedMessage;
use App\MessageHandler\PaymentInitiatedHandler;
use App\Service\FraudChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class PaymentInitiatedHandlerTest extends TestCase
{
    private PaymentInitiatedHandler $handler;
    private MessageBusInterface $busMock;
    private FraudChecker $fraudChecker;

    protected function setUp(): void
    {
        $this->fraudChecker = new FraudChecker();
        $this->busMock = $this->createMock(MessageBusInterface::class);
        $logger = new \Psr\Log\NullLogger();

        $this->handler = new PaymentInitiatedHandler(
            fraudChecker: $this->fraudChecker,
            bus: $this->busMock,
            logger: $logger,
        );
    }

    public function test_handler_publishes_payment_processed_message(): void
    {
        $message = new PaymentInitiatedMessage(
            transactionId: 'txn-123',
            userId: 'user-456',
            amount: 100.0,
            currency: 'USD',
            paymentMethod: 'credit_card',
            customerEmail: 'customer@example.com',
            correlationId: 'corr-789',
        );

        $this->busMock->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (PaymentProcessedMessage $msg) {
                $this->assertSame('txn-123', $msg->transactionId);
                $this->assertSame('corr-789', $msg->correlationId);
                $this->assertFalse($msg->highRisk);
                $this->assertGreaterThanOrEqual(0.0, $msg->riskScore);
                $this->assertNotEmpty($msg->reason);
                return true;
            }))
            ->willReturn(new Envelope(new \stdClass()));

        ($this->handler)($message);
    }

    public function test_high_risk_amount_sets_high_risk_flag(): void
    {
        $message = new PaymentInitiatedMessage(
            transactionId: 'txn-999',
            userId: 'user-1',
            amount: 5000.0,
            currency: 'EUR',
            paymentMethod: 'bank_transfer',
            customerEmail: 'big@spender.com',
            correlationId: 'corr-999',
        );

        $this->busMock->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (PaymentProcessedMessage $msg) {
                $this->assertTrue($msg->highRisk);
                $this->assertGreaterThan(0.5, $msg->riskScore);
                return true;
            }))
            ->willReturn(new Envelope(new \stdClass()));

        ($this->handler)($message);
    }
}

