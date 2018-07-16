<?php declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20180715173631 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE user DROP plain_password, CHANGE id id INT AUTO_INCREMENT NOT NULL, CHANGE username username VARCHAR(255) NOT NULL, CHANGE roles roles LONGTEXT NOT NULL COMMENT \'(DC2Type:array)\'');
        $this->addSql('ALTER TABLE category CHANGE id id INT AUTO_INCREMENT NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_64C19C15E237E06 ON category (name)');
        $this->addSql('ALTER TABLE post CHANGE id id INT AUTO_INCREMENT NOT NULL, CHANGE image_path image_path VARCHAR(255) DEFAULT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE edited_at edited_at DATETIME NOT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP INDEX UNIQ_64C19C15E237E06 ON category');
        $this->addSql('ALTER TABLE category CHANGE id id INT UNSIGNED AUTO_INCREMENT NOT NULL');
        $this->addSql('ALTER TABLE post CHANGE id id INT UNSIGNED AUTO_INCREMENT NOT NULL, CHANGE created_at created_at DATETIME DEFAULT NULL, CHANGE edited_at edited_at DATETIME DEFAULT NULL, CHANGE image_path image_path VARCHAR(255) NOT NULL COLLATE latin1_swedish_ci');
        $this->addSql('ALTER TABLE user ADD plain_password VARCHAR(255) DEFAULT NULL COLLATE latin1_swedish_ci, CHANGE id id INT UNSIGNED AUTO_INCREMENT NOT NULL, CHANGE username username VARCHAR(255) DEFAULT NULL COLLATE latin1_swedish_ci, CHANGE roles roles VARCHAR(255) DEFAULT NULL COLLATE latin1_swedish_ci');
    }
}
