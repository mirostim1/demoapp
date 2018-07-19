<?php declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20180719090123 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE post DROP INDEX image_id, ADD UNIQUE INDEX UNIQ_5A8A6C8D3DA5256D (image_id)');
        $this->addSql('ALTER TABLE post DROP FOREIGN KEY post_ibfk_1');
        $this->addSql('ALTER TABLE post DROP FOREIGN KEY post_ibfk_2');
        $this->addSql('ALTER TABLE post DROP FOREIGN KEY post_ibfk_3');
        $this->addSql('ALTER TABLE post CHANGE id id INT AUTO_INCREMENT NOT NULL, CHANGE category_id category_id INT NOT NULL, CHANGE user_id user_id INT NOT NULL, CHANGE title title VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE post ADD CONSTRAINT FK_5A8A6C8D12469DE2 FOREIGN KEY (category_id) REFERENCES category (id)');
        $this->addSql('ALTER TABLE post ADD CONSTRAINT FK_5A8A6C8DA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE post ADD CONSTRAINT FK_5A8A6C8D3DA5256D FOREIGN KEY (image_id) REFERENCES image (id)');
        $this->addSql('ALTER TABLE post RENAME INDEX category_id TO IDX_5A8A6C8D12469DE2');
        $this->addSql('ALTER TABLE post RENAME INDEX user_id TO IDX_5A8A6C8DA76ED395');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE `post` DROP INDEX UNIQ_5A8A6C8D3DA5256D, ADD INDEX image_id (image_id)');
        $this->addSql('ALTER TABLE `post` DROP FOREIGN KEY FK_5A8A6C8D12469DE2');
        $this->addSql('ALTER TABLE `post` DROP FOREIGN KEY FK_5A8A6C8DA76ED395');
        $this->addSql('ALTER TABLE `post` DROP FOREIGN KEY FK_5A8A6C8D3DA5256D');
        $this->addSql('ALTER TABLE `post` CHANGE id id INT UNSIGNED AUTO_INCREMENT NOT NULL, CHANGE category_id category_id INT DEFAULT NULL, CHANGE user_id user_id INT DEFAULT NULL, CHANGE title title VARCHAR(11) NOT NULL COLLATE utf8_general_ci');
        $this->addSql('ALTER TABLE `post` ADD CONSTRAINT post_ibfk_1 FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE `post` ADD CONSTRAINT post_ibfk_2 FOREIGN KEY (category_id) REFERENCES category (id) ON UPDATE CASCADE ON DELETE SET NULL');
        $this->addSql('ALTER TABLE `post` ADD CONSTRAINT post_ibfk_3 FOREIGN KEY (image_id) REFERENCES image (id) ON UPDATE CASCADE ON DELETE SET NULL');
        $this->addSql('ALTER TABLE `post` RENAME INDEX idx_5a8a6c8da76ed395 TO user_id');
        $this->addSql('ALTER TABLE `post` RENAME INDEX idx_5a8a6c8d12469de2 TO category_id');
    }
}
