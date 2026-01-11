<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260111120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove GameSession entity and clean up redundant relationships';
    }

    public function up(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();

        // Disable FK checks to allow dropping tables with references
        $this->addSql('SET FOREIGN_KEY_CHECKS = 0');

        // Drop foreign key from blackjack_hand to game_session (if table and column exist)
        if ($schemaManager->tablesExist(['blackjack_hand'])) {
            $columns = $schemaManager->listTableColumns('blackjack_hand');
            if (isset($columns['game_session_id'])) {
                $fks = $schemaManager->listTableForeignKeys('blackjack_hand');
                foreach ($fks as $fk) {
                    if (in_array('game_session_id', $fk->getLocalColumns())) {
                        $this->addSql('ALTER TABLE blackjack_hand DROP FOREIGN KEY ' . $fk->getName());
                        break;
                    }
                }
                $this->addSql('ALTER TABLE blackjack_hand DROP game_session_id');
            }
        }

        // Drop foreign key from bet to game_session (if column exists)
        $betColumns = $schemaManager->listTableColumns('bet');
        if (isset($betColumns['game_session_id'])) {
            $fks = $schemaManager->listTableForeignKeys('bet');
            foreach ($fks as $fk) {
                if (in_array('game_session_id', $fk->getLocalColumns())) {
                    $this->addSql('ALTER TABLE bet DROP FOREIGN KEY ' . $fk->getName());
                    break;
                }
            }
            $this->addSql('ALTER TABLE bet DROP game_session_id');
        }

        // Drop the game_session table if it exists
        if ($schemaManager->tablesExist(['game_session'])) {
            $this->addSql('DROP TABLE game_session');
        }

        // Re-enable FK checks
        $this->addSql('SET FOREIGN_KEY_CHECKS = 1');
    }

    public function down(Schema $schema): void
    {
        // Recreate game_session table
        $this->addSql('CREATE TABLE game_session (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            game_type VARCHAR(20) NOT NULL,
            status VARCHAR(20) NOT NULL,
            started_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            finished_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_4586AAFBA76ED395 (user_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE game_session ADD CONSTRAINT FK_4586AAFBA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');

        // Add game_session_id back to bet
        $this->addSql('ALTER TABLE bet ADD game_session_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE bet ADD CONSTRAINT FK_FBF0EC9B8FE32B32 FOREIGN KEY (game_session_id) REFERENCES game_session (id)');
        $this->addSql('CREATE INDEX IDX_FBF0EC9B8FE32B32 ON bet (game_session_id)');

        // Add game_session_id back to blackjack_hand
        $this->addSql('ALTER TABLE blackjack_hand ADD game_session_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE blackjack_hand ADD CONSTRAINT FK_BLACKJACK_SESSION FOREIGN KEY (game_session_id) REFERENCES game_session (id)');
        $this->addSql('CREATE INDEX IDX_BLACKJACK_SESSION ON blackjack_hand (game_session_id)');
    }
}
