<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260108120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add shared roulette rounds and link bets to rounds and users';
    }

    public function up(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();

        if (!$this->tableExists($schemaManager, 'roulette_round')) {
            $this->addSql('CREATE TABLE roulette_round (id INT AUTO_INCREMENT NOT NULL, started_at DATETIME NOT NULL, ends_at DATETIME NOT NULL, status VARCHAR(20) NOT NULL, result_number INT DEFAULT NULL, result_color VARCHAR(10) DEFAULT NULL, resolved_at DATETIME DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        }

        if ($this->tableExists($schemaManager, 'bet')) {
            if (!$this->columnExists($schemaManager, 'bet', 'round_id')) {
                $this->addSql('ALTER TABLE bet ADD round_id INT DEFAULT NULL');
            }
            if (!$this->columnExists($schemaManager, 'bet', 'user_id')) {
                $this->addSql('ALTER TABLE bet ADD user_id INT DEFAULT NULL');
            }

            if ($this->columnExists($schemaManager, 'bet', 'game_session_id')) {
                $gameSessionColumn = $schemaManager->listTableColumns('bet')['game_session_id'] ?? null;
                if ($gameSessionColumn !== null && $gameSessionColumn->getNotnull()) {
                    $this->addSql('ALTER TABLE bet MODIFY game_session_id INT DEFAULT NULL');
                }
            }

            if ($this->columnExists($schemaManager, 'bet', 'user_id') && $this->columnExists($schemaManager, 'bet', 'game_session_id')) {
                $this->addSql('UPDATE bet b INNER JOIN game_session gs ON gs.id = b.game_session_id SET b.user_id = gs.user_id WHERE b.game_session_id IS NOT NULL AND b.user_id IS NULL');
            }

            if ($this->tableExists($schemaManager, 'roulette_round')) {
                $this->addSql('INSERT INTO roulette_round (id, started_at, ends_at, status, result_number, result_color, resolved_at) SELECT DISTINCT gs.id, gs.started_at, COALESCE(rs.spun_at, gs.finished_at, DATE_ADD(gs.started_at, INTERVAL 30 SECOND)), CASE WHEN rs.id IS NULL THEN \'open\' ELSE \'finished\' END, rs.result_number, rs.result_color, rs.spun_at FROM game_session gs INNER JOIN bet b ON b.game_session_id = gs.id LEFT JOIN roulette_spin rs ON rs.game_session_id = gs.id WHERE NOT EXISTS (SELECT 1 FROM roulette_round rr WHERE rr.id = gs.id)');
            }

            if ($this->columnExists($schemaManager, 'bet', 'round_id') && $this->columnExists($schemaManager, 'bet', 'game_session_id')) {
                $this->addSql('UPDATE bet SET round_id = game_session_id WHERE game_session_id IS NOT NULL AND round_id IS NULL');
            }

            if ($this->columnExists($schemaManager, 'bet', 'round_id') && $this->tableExists($schemaManager, 'roulette_round')) {
                $this->addSql('UPDATE bet SET round_id = NULL WHERE round_id IS NOT NULL AND round_id <= 0');
                $this->addSql('UPDATE bet b LEFT JOIN roulette_round rr ON rr.id = b.round_id SET b.round_id = b.game_session_id WHERE b.round_id IS NOT NULL AND rr.id IS NULL AND b.game_session_id IS NOT NULL');
                $this->addSql('INSERT INTO roulette_round (id, started_at, ends_at, status) SELECT DISTINCT b.round_id, NOW(), DATE_ADD(NOW(), INTERVAL 30 SECOND), \'finished\' FROM bet b LEFT JOIN roulette_round rr ON rr.id = b.round_id WHERE b.round_id IS NOT NULL AND b.round_id > 0 AND rr.id IS NULL');
                $this->addSql('UPDATE bet b LEFT JOIN roulette_round rr ON rr.id = b.round_id SET b.round_id = NULL WHERE b.round_id IS NOT NULL AND rr.id IS NULL');
            }

            if ($this->columnExists($schemaManager, 'bet', 'round_id') && $this->columnExists($schemaManager, 'bet', 'user_id')) {
                $nullCount = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM bet WHERE round_id IS NULL OR user_id IS NULL');
                if ($nullCount === 0) {
                    $this->addSql('ALTER TABLE bet MODIFY round_id INT NOT NULL, MODIFY user_id INT NOT NULL');
                }
            }

            if (!$this->foreignKeyExists($schemaManager, 'bet', 'FK_FBF0EC9B7B1DA0E2') && $this->columnExists($schemaManager, 'bet', 'round_id')) {
                $this->addSql('ALTER TABLE bet ADD CONSTRAINT FK_FBF0EC9B7B1DA0E2 FOREIGN KEY (round_id) REFERENCES roulette_round (id)');
            }
            if (!$this->foreignKeyExists($schemaManager, 'bet', 'FK_FBF0EC9BA76ED395') && $this->columnExists($schemaManager, 'bet', 'user_id')) {
                $this->addSql('ALTER TABLE bet ADD CONSTRAINT FK_FBF0EC9BA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
            }

            if (!$this->indexExists($schemaManager, 'bet', 'IDX_FBF0EC9B7B1DA0E2') && $this->columnExists($schemaManager, 'bet', 'round_id')) {
                $this->addSql('CREATE INDEX IDX_FBF0EC9B7B1DA0E2 ON bet (round_id)');
            }
            if (!$this->indexExists($schemaManager, 'bet', 'IDX_FBF0EC9BA76ED395') && $this->columnExists($schemaManager, 'bet', 'user_id')) {
                $this->addSql('CREATE INDEX IDX_FBF0EC9BA76ED395 ON bet (user_id)');
            }
        }
    }

    private function tableExists($schemaManager, string $table): bool
    {
        return in_array($table, $schemaManager->listTableNames(), true);
    }

    private function columnExists($schemaManager, string $table, string $column): bool
    {
        if (!$this->tableExists($schemaManager, $table)) {
            return false;
        }

        $columns = $schemaManager->listTableColumns($table);
        return array_key_exists($column, $columns);
    }

    private function foreignKeyExists($schemaManager, string $table, string $name): bool
    {
        if (!$this->tableExists($schemaManager, $table)) {
            return false;
        }

        foreach ($schemaManager->listTableForeignKeys($table) as $foreignKey) {
            if ($foreignKey->getName() === $name) {
                return true;
            }
        }

        return false;
    }

    private function indexExists($schemaManager, string $table, string $name): bool
    {
        if (!$this->tableExists($schemaManager, $table)) {
            return false;
        }

        $indexes = $schemaManager->listTableIndexes($table);
        return array_key_exists($name, $indexes);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE bet DROP FOREIGN KEY FK_FBF0EC9B7B1DA0E2');
        $this->addSql('ALTER TABLE bet DROP FOREIGN KEY FK_FBF0EC9BA76ED395');
        $this->addSql('DROP TABLE roulette_round');
        $this->addSql('DROP INDEX IDX_FBF0EC9B7B1DA0E2 ON bet');
        $this->addSql('DROP INDEX IDX_FBF0EC9BA76ED395 ON bet');
        $this->addSql('ALTER TABLE bet DROP round_id, DROP user_id');
        $this->addSql('ALTER TABLE bet MODIFY game_session_id INT NOT NULL');
    }
}
