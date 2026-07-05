<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260702101316 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE comment ADD is_hidden BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE comment ALTER COLUMN is_hidden DROP DEFAULT');
        $this->addSql('ALTER TABLE "user" ADD is_verified BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE "user" ALTER COLUMN is_verified DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE comment DROP is_hidden');
        $this->addSql('ALTER TABLE "user" DROP is_verified');
    }
}
