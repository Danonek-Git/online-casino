<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260110120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add blackjack_hand table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE blackjack_hand (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            game_session_id INT DEFAULT NULL,
            player_cards JSON NOT NULL,
            dealer_cards JSON NOT NULL,
            bet_amount INT NOT NULL,
            status VARCHAR(20) NOT NULL,
            result VARCHAR(20) DEFAULT NULL,
            payout INT DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            finished_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_BLACKJACK_USER (user_id),
            INDEX IDX_BLACKJACK_SESSION (game_session_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE blackjack_hand ADD CONSTRAINT FK_BLACKJACK_USER FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE blackjack_hand ADD CONSTRAINT FK_BLACKJACK_SESSION FOREIGN KEY (game_session_id) REFERENCES game_session (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE blackjack_hand DROP FOREIGN KEY FK_BLACKJACK_USER');
        $this->addSql('ALTER TABLE blackjack_hand DROP FOREIGN KEY FK_BLACKJACK_SESSION');
        $this->addSql('DROP TABLE blackjack_hand');
    }
}
