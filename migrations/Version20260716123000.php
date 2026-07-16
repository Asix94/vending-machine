<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260716123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop fixed selector constraint to use machine_products as the product catalog source of truth';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE machine_products DROP CONSTRAINT IF EXISTS machine_products_selector_allowed');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE machine_products ADD CONSTRAINT machine_products_selector_allowed CHECK (selector IN ('WATER', 'JUICE', 'SODA'))");
    }
}
