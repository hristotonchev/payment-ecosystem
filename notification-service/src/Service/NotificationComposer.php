<?php

namespace App\Service;

use App\Message\PaymentNotificationMessage;

class NotificationComposer
{
    public function compose(PaymentNotificationMessage $message): array
    {
        if ($message->recipientType === 'admin' && $message->status === 'high_risk') {
            return [
                'subject' => 'Fraud Alert: High-Risk Payment Detected',
                'body'    => $this->formatAdminNotification($message),
            ];
        }

        return [
            'subject' => 'Payment Confirmation',
            'body'    => $this->formatCustomerNotification($message),
        ];
    }

    private function formatAdminNotification(PaymentNotificationMessage $message): string
    {
        return sprintf(
            "⚠️ High-Risk Payment Alert\n\n" .
            "Transaction ID: %s\n" .
            "Amount: %.2f %s\n" .
            "Status: %s\n" .
            "Recipient: %s\n\n" .
            "Please review this transaction for potential fraud.",
            $message->transactionId,
            $message->amount,
            $message->currency,
            $message->status,
            $message->recipientEmail,
        );
    }

    private function formatCustomerNotification(PaymentNotificationMessage $message): string
    {
        return sprintf(
            "Thank you for your payment!\n\n" .
            "Transaction ID: %s\n" .
            "Amount: %.2f %s\n" .
            "Status: %s\n\n" .
            "Your payment has been processed successfully.",
            $message->transactionId,
            $message->amount,
            $message->currency,
            ucfirst($message->status),
        );
    }
}

