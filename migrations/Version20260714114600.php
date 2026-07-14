<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260714114600 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create wallet tables for inserted balance and inserted coin counts';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE wallets (id UUID NOT NULL, inserted_balance_cents INT NOT NULL DEFAULT 0, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE wallet_inserted_coins (wallet_id UUID NOT NULL, coin_cents SMALLINT NOT NULL, coin_count INT NOT NULL DEFAULT 0, PRIMARY KEY(wallet_id, coin_cents))');
        $this->addSql('ALTER TABLE wallets ADD CONSTRAINT wallets_balance_non_negative CHECK (inserted_balance_cents >= 0)');
        $this->addSql('ALTER TABLE wallet_inserted_coins ADD CONSTRAINT wallet_inserted_coins_coin_allowed CHECK (coin_cents IN (5, 10, 25, 100))');
        $this->addSql('ALTER TABLE wallet_inserted_coins ADD CONSTRAINT wallet_inserted_coins_count_non_negative CHECK (coin_count >= 0)');
        $this->addSql('ALTER TABLE wallet_inserted_coins ADD CONSTRAINT fk_wallet_inserted_coins_wallet FOREIGN KEY (wallet_id) REFERENCES wallets (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE wallet_inserted_coins');
        $this->addSql('DROP TABLE wallets');
    }
}
