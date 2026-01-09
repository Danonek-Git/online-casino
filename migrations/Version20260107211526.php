<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260107211526 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE bet (id INT AUTO_INCREMENT NOT NULL, bet_type VARCHAR(20) NOT NULL, bet_value VARCHAR(20) NOT NULL, amount INT NOT NULL, payout INT DEFAULT NULL, is_win TINYINT DEFAULT NULL, placed_at DATETIME NOT NULL, game_session_id INT NOT NULL, INDEX IDX_FBF0EC9B8FE32B32 (game_session_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE game_session (id INT AUTO_INCREMENT NOT NULL, game_type VARCHAR(20) NOT NULL, status VARCHAR(20) NOT NULL, started_at DATETIME NOT NULL, finished_at DATETIME DEFAULT NULL, user_id INT NOT NULL, INDEX IDX_4586AAFBA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE roulette_spin (id INT AUTO_INCREMENT NOT NULL, result_number INT NOT NULL, result_color VARCHAR(10) NOT NULL, spun_at DATETIME NOT NULL, game_session_id INT NOT NULL, UNIQUE INDEX UNIQ_3C49F4A28FE32B32 (game_session_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE bet ADD CONSTRAINT FK_FBF0EC9B8FE32B32 FOREIGN KEY (game_session_id) REFERENCES game_session (id)');
        $this->addSql('ALTER TABLE game_session ADD CONSTRAINT FK_4586AAFBA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE roulette_spin ADD CONSTRAINT FK_3C49F4A28FE32B32 FOREIGN KEY (game_session_id) REFERENCES game_session (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE bet DROP FOREIGN KEY FK_FBF0EC9B8FE32B32');
        $this->addSql('ALTER TABLE game_session DROP FOREIGN KEY FK_4586AAFBA76ED395');
        $this->addSql('ALTER TABLE roulette_spin DROP FOREIGN KEY FK_3C49F4A28FE32B32');
        $this->addSql('DROP TABLE bet');
        $this->addSql('DROP TABLE game_session');
        $this->addSql('DROP TABLE roulette_spin');
    }
}
