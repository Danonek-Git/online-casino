<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260111130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove unused tables: article, messenger_messages, roulette_spin';
    }

    public function up(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();

        if ($schemaManager->tablesExist(['article'])) {
            $this->addSql('DROP TABLE article');
        }

        if ($schemaManager->tablesExist(['messenger_messages'])) {
            $this->addSql('DROP TABLE messenger_messages');
        }

        if ($schemaManager->tablesExist(['roulette_spin'])) {
            $this->addSql('DROP TABLE roulette_spin');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE article (
            id INT AUTO_INCREMENT NOT NULL,
            title VARCHAR(200) NOT NULL,
            image_path VARCHAR(255) NOT NULL,
            content LONGTEXT DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }
}
