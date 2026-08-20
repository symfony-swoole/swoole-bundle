<?php

declare(strict_types=1);

namespace TestBundleDoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;

/**
 * The table a group of messenger consumers writes into - see
 * {@see \SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Entity\ConsumedMessage}.
 */
final class Version20260820094500 extends AbstractMigration
{
    #[Override]
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            <<<'SQL'
                    CREATE TABLE consumed_message (
                        id INT AUTO_INCREMENT NOT NULL,
                        message_id VARCHAR(64) NOT NULL,
                        coroutine_id INT NOT NULL,
                        worker_pid INT NOT NULL,
                        INDEX message_id_idx (message_id),
                        PRIMARY KEY(id)
                    ) DEFAULT CHARACTER SET utf8 ENGINE = InnoDB
                SQL
        );
    }

    #[Override]
    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE consumed_message');
    }

    #[Override]
    public function isTransactional(): bool
    {
        return false;
    }
}
