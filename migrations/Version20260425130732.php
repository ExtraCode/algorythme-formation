<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260425130732 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE smart_of_auth (id INT AUTO_INCREMENT NOT NULL, refresh_token VARCHAR(255) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE avis_formation ADD CONSTRAINT FK_E6153E7E5200282E FOREIGN KEY (formation_id) REFERENCES formation (id)');
        $this->addSql('ALTER TABLE chapitre_module_formation ADD CONSTRAINT FK_8736B71F3A53B0DC FOREIGN KEY (module_formation_id) REFERENCES module_formation (id)');
        $this->addSql('ALTER TABLE formation ADD CONSTRAINT FK_404021BFB3E9C81 FOREIGN KEY (niveau_id) REFERENCES niveau_formation (id)');
        $this->addSql('ALTER TABLE formation_thematique_formation ADD CONSTRAINT FK_3434FF8C5200282E FOREIGN KEY (formation_id) REFERENCES formation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE formation_thematique_formation ADD CONSTRAINT FK_3434FF8CDF15B99C FOREIGN KEY (thematique_formation_id) REFERENCES thematique_formation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE inscription_inter_formation ADD CONSTRAINT FK_E01F381C5200282E FOREIGN KEY (formation_id) REFERENCES formation (id)');
        $this->addSql('ALTER TABLE module_formation ADD CONSTRAINT FK_1A213E775200282E FOREIGN KEY (formation_id) REFERENCES formation (id)');
        $this->addSql('ALTER TABLE objectif_formation ADD CONSTRAINT FK_400F6A95200282E FOREIGN KEY (formation_id) REFERENCES formation (id)');
        $this->addSql('ALTER TABLE prerequis_formation ADD CONSTRAINT FK_2C9856195200282E FOREIGN KEY (formation_id) REFERENCES formation (id)');
        $this->addSql('ALTER TABLE public_formation ADD CONSTRAINT FK_6D67FA3E5200282E FOREIGN KEY (formation_id) REFERENCES formation (id)');
        $this->addSql('ALTER TABLE thematique_formation ADD CONSTRAINT FK_A361A3054272FC9F FOREIGN KEY (domaine_id) REFERENCES domaine_formation (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE smart_of_auth');
        $this->addSql('ALTER TABLE avis_formation DROP FOREIGN KEY FK_E6153E7E5200282E');
        $this->addSql('ALTER TABLE chapitre_module_formation DROP FOREIGN KEY FK_8736B71F3A53B0DC');
        $this->addSql('ALTER TABLE formation DROP FOREIGN KEY FK_404021BFB3E9C81');
        $this->addSql('ALTER TABLE formation_thematique_formation DROP FOREIGN KEY FK_3434FF8C5200282E');
        $this->addSql('ALTER TABLE formation_thematique_formation DROP FOREIGN KEY FK_3434FF8CDF15B99C');
        $this->addSql('ALTER TABLE inscription_inter_formation DROP FOREIGN KEY FK_E01F381C5200282E');
        $this->addSql('ALTER TABLE module_formation DROP FOREIGN KEY FK_1A213E775200282E');
        $this->addSql('ALTER TABLE objectif_formation DROP FOREIGN KEY FK_400F6A95200282E');
        $this->addSql('ALTER TABLE prerequis_formation DROP FOREIGN KEY FK_2C9856195200282E');
        $this->addSql('ALTER TABLE public_formation DROP FOREIGN KEY FK_6D67FA3E5200282E');
        $this->addSql('ALTER TABLE thematique_formation DROP FOREIGN KEY FK_A361A3054272FC9F');
    }
}
