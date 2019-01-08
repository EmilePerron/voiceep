<?php declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20180929231157 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE project (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, identifier VARCHAR(45) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE entry (id INT AUTO_INCREMENT NOT NULL, project_id INT NOT NULL, url LONGTEXT NOT NULL, INDEX IDX_2B219D70166D1F9C (project_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE entry ADD CONSTRAINT FK_2B219D70166D1F9C FOREIGN KEY (project_id) REFERENCES project (id)');
        $this->addSql('ALTER TABLE voiceover ADD entry_id INT NOT NULL');
        $this->addSql('ALTER TABLE voiceover ADD CONSTRAINT FK_385FEC5CBA364942 FOREIGN KEY (entry_id) REFERENCES entry (id)');
        $this->addSql('CREATE INDEX IDX_385FEC5CBA364942 ON voiceover (entry_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE entry DROP FOREIGN KEY FK_2B219D70166D1F9C');
        $this->addSql('ALTER TABLE voiceover DROP FOREIGN KEY FK_385FEC5CBA364942');
        $this->addSql('DROP TABLE project');
        $this->addSql('DROP TABLE entry');
        $this->addSql('DROP INDEX IDX_385FEC5CBA364942 ON voiceover');
        $this->addSql('ALTER TABLE voiceover DROP entry_id');
    }
}
