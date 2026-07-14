<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260714170123 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create vending machine product and coin inventory tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE machine_products (selector VARCHAR(16) NOT NULL, price_cents INT NOT NULL, stock INT NOT NULL DEFAULT 0, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(selector))');
        $this->addSql('CREATE TABLE machine_coins (coin_cents SMALLINT NOT NULL, coin_count INT NOT NULL DEFAULT 0, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(coin_cents))');

        $this->addSql("ALTER TABLE machine_products ADD CONSTRAINT machine_products_selector_allowed CHECK (selector IN ('WATER', 'JUICE', 'SODA'))");
        $this->addSql('ALTER TABLE machine_products ADD CONSTRAINT machine_products_price_positive CHECK (price_cents > 0)');
        $this->addSql('ALTER TABLE machine_products ADD CONSTRAINT machine_products_stock_non_negative CHECK (stock >= 0)');

        $this->addSql('ALTER TABLE machine_coins ADD CONSTRAINT machine_coins_coin_allowed CHECK (coin_cents IN (5, 10, 25, 100))');
        $this->addSql('ALTER TABLE machine_coins ADD CONSTRAINT machine_coins_count_non_negative CHECK (coin_count >= 0)');

        $this->addSql("INSERT INTO machine_products (selector, price_cents, stock, updated_at) VALUES ('WATER', 65, 0, NOW()), ('JUICE', 100, 0, NOW()), ('SODA', 150, 0, NOW())");
        $this->addSql('INSERT INTO machine_coins (coin_cents, coin_count, updated_at) VALUES (5, 0, NOW()), (10, 0, NOW()), (25, 0, NOW()), (100, 0, NOW())');

    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE machine_coins');
        $this->addSql('DROP TABLE machine_products');

    }
}
