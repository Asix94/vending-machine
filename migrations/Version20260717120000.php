<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260717120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Set default machine stock and coin inventory to 10 for all catalog rows';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('UPDATE machine_coins SET coin_count = 10, updated_at = NOW()');
        $this->addSql('UPDATE machine_products SET stock = 10, updated_at = NOW()');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('UPDATE machine_coins SET coin_count = 0, updated_at = NOW()');
        $this->addSql('UPDATE machine_products SET stock = 0, updated_at = NOW()');
    }
}
