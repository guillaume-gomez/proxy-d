<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251202175302 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the Video table to store basic info about a monitored videos';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "
            CREATE TABLE videos (
                id VARCHAR(10) PRIMARY KEY UNIQUE NOT NULL,
                status VARCHAR(10) DEFAULT 'pending',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )"
        );

    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE videos');
    }
}
