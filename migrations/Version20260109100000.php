<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260109100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add admin articles and user block flag';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD is_blocked TINYINT(1) NOT NULL DEFAULT 0');
        $this->addSql('CREATE TABLE article (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(200) NOT NULL, image_path VARCHAR(255) NOT NULL, content LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('INSERT INTO article (title, image_path, content, created_at) VALUES 
            (\'Ruletka nocna\', \'/assets/articles/roulette.webp\', \'Szybkie rundy, wspólne losowania i świeże emocje.\', NOW()),
            (\'Blackjack w przygotowaniu\', \'/assets/articles/blackjack.webp\', \'Stół jest w drodze. Już niedługo więcej kart.\', NOW()),
            (\'Sloty – testujemy\', \'/assets/articles/slots.webp\', \'Nowe automaty i bonusowe tryby już niedługo.\', NOW())');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE article');
        $this->addSql('ALTER TABLE user DROP is_blocked');
    }
}
