<?php

namespace App\Tests\Unit\Service;

use App\Message\PaymentNotificationMessage;
use App\Service\NotificationComposer;
use PHPUnit\Framework\TestCase;

class NotificationComposerTest extends TestCase
{
    private NotificationComposer $composer;

    protected function setUp(): void
    {
        $this->composer = new NotificationComposer();
    }

    public function test_admin_notification_for_high_risk(): void
    {
        $message = new PaymentNotificationMessage(
            transactionId: 'txn-123',
            recipientEmail: 'admin@example.com',
            recipientType: 'admin',
            status: 'high_risk',
            amount: 5000.0,
            currency: 'USD',
            correlationId: 'corr-789',
        );

        $notification = $this->composer->compose($message);

        $this->assertStringContainsString('Fraud Alert', $notification['subject']);
        $this->assertStringContainsString('High-Risk', $notification['body']);
    }

    public function test_customer_notification_for_accepted(): void
    {
        $message = new PaymentNotificationMessage(
            transactionId: 'txn-123',
            recipientEmail: 'customer@example.com',
            recipientType: 'customer',
            status: 'accepted',
            amount: 100.0,
            currency: 'USD',
            correlationId: 'corr-789',
        );

        $notification = $this->composer->compose($message);

        $this->assertStringContainsString('Confirmation', $notification['subject']);
        $this->assertStringContainsString('processed successfully', $notification['body']);
    }
}

