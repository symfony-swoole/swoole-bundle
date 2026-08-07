<?php

declare(strict_types=1);

namespace TestBundleDoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260723150108 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE symfony_session (id VARBINARY(128) NOT NULL, '
            . 'data LONGBLOB NOT NULL, lifetime INT UNSIGNED NOT NULL, '
            . 'time INT UNSIGNED NOT NULL, '
            . 'INDEX lifetime_idx (lifetime), '
            . 'PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8 ENGINE = InnoDB',
        );
        $this->addSql('ALTER TABLE advanced_test CHANGE uuid uuid CHAR(36) NOT NULL');
        $this->addSql('ALTER TABLE test CHANGE uuid uuid CHAR(36) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE symfony_session');
        $this->addSql('ALTER TABLE advanced_test CHANGE uuid uuid CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE test CHANGE uuid uuid CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\'');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
