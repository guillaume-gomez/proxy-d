<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251203203647 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add an index to optimise video query';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX idx_video_created_at ON videos(created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_video_created_at ON videos');
    }
}
