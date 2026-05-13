<?php

namespace App\Tests\Integration\Controller;

use App\Entity\Transaction;
use App\Enum\TransactionStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

class PaymentControllerTest extends WebTestCase
{
    public function test_initiate_creates_pending_transaction_and_dispatches_message(): void
    {
        $client = self::createClient();

        $client->request(
            'POST',
            '/api/payments/initiate',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'user_id'        => 'user-100',
                'amount'         => 150.00,
                'currency'       => 'USD',
                'payment_method' => 'credit_card',
                'customer_email' => 'customer@example.com',
            ])
        );

        $this->assertResponseStatusCodeSame(201);

        $body = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('transaction_id', $body);
        $this->assertArrayHasKey('correlation_id', $body);
        $this->assertSame('pending', $body['status']);

        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $transaction = $em->find(Transaction::class, $body['transaction_id']);

        $this->assertNotNull($transaction);
        $this->assertSame(TransactionStatus::Pending, $transaction->getStatus());
        $this->assertSame('USD', $transaction->getCurrency());
        $this->assertNotEmpty($transaction->getCorrelationId());

        /** @var InMemoryTransport $transport */
        $transport = self::getContainer()->get('messenger.transport.payment_initiated');
        $this->assertCount(1, $transport->getSent());
    }

    public function test_initiate_returns_422_when_required_field_missing(): void
    {
        $client = self::createClient();

        $client->request(
            'POST',
            '/api/payments/initiate',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'amount'         => 100.00,
                'currency'       => 'USD',
                'payment_method' => 'credit_card',
            ])
        );

        $this->assertResponseStatusCodeSame(422);
    }

    public function test_initiate_returns_422_when_amount_is_negative(): void
    {
        $client = self::createClient();

        $client->request(
            'POST',
            '/api/payments/initiate',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'user_id'        => 'user-1',
                'amount'         => -10.00,
                'currency'       => 'USD',
                'payment_method' => 'credit_card',
                'customer_email' => 'customer@example.com',
            ])
        );

        $this->assertResponseStatusCodeSame(422);
    }

    public function test_confirm_returns_404_for_unknown_transaction(): void
    {
        $client = self::createClient();

        $client->request('POST', '/api/payments/00000000-0000-0000-0000-000000000000/confirm');

        $this->assertResponseStatusCodeSame(404);
    }
}

