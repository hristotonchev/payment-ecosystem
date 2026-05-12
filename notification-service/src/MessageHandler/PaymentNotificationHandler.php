<?php

namespace App\MessageHandler;

use App\Message\PaymentNotificationMessage;
use App\Service\NotificationComposer;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;

#[AsMessageHandler]
class PaymentNotificationHandler
{
    public function __construct(
        private readonly MailerInterface         $mailer,
        private readonly NotificationComposer   $composer,
        private readonly LoggerInterface        $logger,
        private readonly string                 $mailerFrom = 'noreply@payment-ecosystem.dev',
    ) {}

    public function __invoke(PaymentNotificationMessage $message): void
    {
        try {
            $this->logger->info('Processing payment notification', [
                'transaction_id' => $message->transactionId,
                'recipient_type' => $message->recipientType,
                'correlation_id' => $message->correlationId,
            ]);

            $notification = $this->composer->compose($message);

            $email = (new Email())
                ->from($this->mailerFrom)
                ->to($message->recipientEmail)
                ->subject($notification['subject'])
                ->text($notification['body']);

            $this->mailer->send($email);

            $this->logger->info('Notification sent', [
                'transaction_id' => $message->transactionId,
                'recipient'      => $message->recipientEmail,
                'correlation_id' => $message->correlationId,
            ]);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Failed to send notification', [
                'transaction_id' => $message->transactionId,
                'recipient'      => $message->recipientEmail,
                'correlation_id' => $message->correlationId,
                'error'          => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}

