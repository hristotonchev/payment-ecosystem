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
            correlationId: 'corr-789',
        );

        $this->busMock->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(PaymentProcessedMessage::class))
            ->willReturn(new Envelope(new \stdClass()));

        ($this->handler)($message);
    }
}

