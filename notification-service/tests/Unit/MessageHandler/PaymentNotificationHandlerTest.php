<?php

namespace App\Tests\Unit\MessageHandler;

use App\Message\PaymentNotificationMessage;
use App\MessageHandler\PaymentNotificationHandler;
use App\Service\NotificationComposer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Mailer\MailerInterface;

class PaymentNotificationHandlerTest extends TestCase
{
    private MailerInterface&MockObject $mailer;
    private PaymentNotificationHandler $handler;

    protected function setUp(): void
    {
        $this->mailer = $this->createMock(MailerInterface::class);

        $this->handler = new PaymentNotificationHandler(
            mailer:      $this->mailer,
            composer:    new NotificationComposer(),
            logger:      new NullLogger(),
            mailerFrom:  'noreply@payment-ecosystem.dev',
        );
    }

    public function test_sends_customer_email_for_accepted_payment(): void
    {
        $message = $this->buildMessage(
            recipientEmail: 'customer@example.com',
            recipientType:  'customer',
            status:         'accepted',
        );

        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (\Symfony\Component\Mime\Email $email) {
                $this->assertStringContainsString('Confirmation', $email->getSubject());
                $this->assertSame('customer@example.com', $email->getTo()[0]->getAddress());
                $this->assertStringContainsString('noreply@payment-ecosystem.dev', $email->getFrom()[0]->getAddress());
                return true;
            }));

        ($this->handler)($message);
    }

    public function test_sends_admin_email_for_high_risk_payment(): void
    {
        $message = $this->buildMessage(
            recipientEmail: 'admin@example.com',
            recipientType:  'admin',
            status:         'high_risk',
        );

        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (\Symfony\Component\Mime\Email $email) {
                $this->assertStringContainsString('Fraud Alert', $email->getSubject());
                $this->assertSame('admin@example.com', $email->getTo()[0]->getAddress());
                return true;
            }));

        ($this->handler)($message);
    }

    public function test_rethrows_transport_exception_on_send_failure(): void
    {
        $this->mailer->method('send')
            ->willThrowException(new \Symfony\Component\Mailer\Exception\TransportException('SMTP down'));

        $this->expectException(\Symfony\Component\Mailer\Exception\TransportExceptionInterface::class);

        ($this->handler)($this->buildMessage());
    }

    private function buildMessage(
        string $recipientEmail = 'customer@example.com',
        string $recipientType  = 'customer',
        string $status         = 'accepted',
    ): PaymentNotificationMessage {
        return new PaymentNotificationMessage(
            transactionId:  'txn-001',
            recipientEmail: $recipientEmail,
            recipientType:  $recipientType,
            status:         $status,
            amount:         99.99,
            currency:       'USD',
            correlationId:  'corr-001',
        );
    }
}

