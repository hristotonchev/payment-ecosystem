<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20241201000000 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql("
            CREATE TABLE transactions (
                id              CHAR(36)       NOT NULL,
                user_id         VARCHAR(255)   NOT NULL,
                amount          DECIMAL(10, 2) NOT NULL,
                currency        VARCHAR(3)     NOT NULL,
                payment_method  VARCHAR(50)    NOT NULL,
                customer_email  VARCHAR(255)   NOT NULL,
                status          VARCHAR(20)    NOT NULL,
                correlation_id  VARCHAR(36)    NOT NULL,
                created_at      DATETIME       NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                updated_at      DATETIME       NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE transactions');
    }
}
