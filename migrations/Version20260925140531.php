<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260925140531 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Table requete : besoins saisis dans le champ d'orientation, avec l'empreinte du visiteur (admin).";
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE requete (id INT AUTO_INCREMENT NOT NULL, besoin LONGTEXT NOT NULL, empreinte VARCHAR(64) DEFAULT NULL, formations JSON DEFAULT NULL, cree_le DATETIME NOT NULL, INDEX IDX_REQUETE_CREE_LE (cree_le), INDEX IDX_REQUETE_EMPREINTE (empreinte), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE requete');
    }
}
