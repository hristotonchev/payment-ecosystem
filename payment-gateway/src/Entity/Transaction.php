<?php

namespace App\Entity;

use App\Enum\TransactionStatus;
use App\Repository\TransactionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TransactionRepository::class)]
#[ORM\Table(name: 'transactions')]
class Transaction
{
    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $id;

    #[ORM\Column(length: 255)]
    private string $userId;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $amount;

    #[ORM\Column(length: 3)]
    private string $currency;

    #[ORM\Column(length: 50)]
    private string $paymentMethod;

    #[ORM\Column(length: 255)]
    private string $customerEmail;

    #[ORM\Column(enumType: TransactionStatus::class)]
    private TransactionStatus $status;

    #[ORM\Column(length: 36)]
    private string $correlationId;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        string $id,
        string $userId,
        string $amount,
        string $currency,
        string $paymentMethod,
        string $customerEmail,
        string $correlationId,
    ) {
        $this->id            = $id;
        $this->userId        = $userId;
        $this->amount        = $amount;
        $this->currency      = $currency;
        $this->paymentMethod = $paymentMethod;
        $this->customerEmail = $customerEmail;
        $this->correlationId = $correlationId;
        $this->status        = TransactionStatus::Pending;
        $this->createdAt     = new \DateTimeImmutable();
        $this->updatedAt     = new \DateTimeImmutable();
    }

    public function accept(): void
    {
        $this->status    = TransactionStatus::Accepted;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function reject(): void
    {
        $this->status    = TransactionStatus::Rejected;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): string                  { return $this->id; }
    public function getUserId(): string              { return $this->userId; }
    public function getAmount(): string              { return $this->amount; }
    public function getCurrency(): string            { return $this->currency; }
    public function getPaymentMethod(): string       { return $this->paymentMethod; }
    public function getCustomerEmail(): string       { return $this->customerEmail; }
    public function getStatus(): TransactionStatus   { return $this->status; }
    public function getCorrelationId(): string       { return $this->correlationId; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
}
