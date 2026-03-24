<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260324114835 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE avis_formation (id INT AUTO_INCREMENT NOT NULL, prenom_auteur VARCHAR(50) NOT NULL, nom_auteur VARCHAR(50) NOT NULL, note INT NOT NULL, texte_sur_formateur LONGTEXT DEFAULT NULL, texte_sur_contenu LONGTEXT DEFAULT NULL, texte_sur_salle LONGTEXT DEFAULT NULL, texte_sur_plus_apprecie LONGTEXT NOT NULL, texte_sur_moins_apprecie LONGTEXT NOT NULL, resume LONGTEXT DEFAULT NULL, formation_id INT NOT NULL, INDEX IDX_E6153E7E5200282E (formation_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE chapitre_module_formation (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, ordre INT NOT NULL, module_formation_id INT NOT NULL, INDEX IDX_8736B71F3A53B0DC (module_formation_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE domaine_formation (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, couleur VARCHAR(10) NOT NULL, slug VARCHAR(20) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE formation (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, reference VARCHAR(10) NOT NULL, courte_description LONGTEXT NOT NULL, description LONGTEXT NOT NULL, eligible_cpf TINYINT NOT NULL, prix_inter INT DEFAULT NULL, prix_intra INT DEFAULT NULL, nb_apprenant INT NOT NULL, au_top TINYINT NOT NULL, nb_jour DOUBLE PRECISION NOT NULL, slug VARCHAR(100) NOT NULL, niveau_id INT NOT NULL, UNIQUE INDEX UNIQ_404021BFAEA34913 (reference), UNIQUE INDEX UNIQ_404021BF989D9B62 (slug), INDEX IDX_404021BFB3E9C81 (niveau_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE formation_thematique_formation (formation_id INT NOT NULL, thematique_formation_id INT NOT NULL, INDEX IDX_3434FF8C5200282E (formation_id), INDEX IDX_3434FF8CDF15B99C (thematique_formation_id), PRIMARY KEY (formation_id, thematique_formation_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE inscription_inter_formation (id INT AUTO_INCREMENT NOT NULL, date_debut DATE NOT NULL, date_fin DATE NOT NULL, email VARCHAR(255) NOT NULL, telephone VARCHAR(15) DEFAULT NULL, nom VARCHAR(50) NOT NULL, prenom VARCHAR(50) NOT NULL, type VARCHAR(50) NOT NULL, adresse VARCHAR(255) NOT NULL, code_postal VARCHAR(7) NOT NULL, ville VARCHAR(70) NOT NULL, message LONGTEXT DEFAULT NULL, communication VARCHAR(255) NOT NULL, formation_id INT NOT NULL, INDEX IDX_E01F381C5200282E (formation_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE module_formation (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, ordre INT NOT NULL, formation_id INT NOT NULL, INDEX IDX_1A213E775200282E (formation_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE niveau_formation (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE objectif_formation (id INT AUTO_INCREMENT NOT NULL, texte VARCHAR(255) NOT NULL, ordre INT NOT NULL, formation_id INT NOT NULL, INDEX IDX_400F6A95200282E (formation_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE prerequis_formation (id INT AUTO_INCREMENT NOT NULL, titre VARCHAR(255) NOT NULL, ordre INT NOT NULL, formation_id INT NOT NULL, INDEX IDX_2C9856195200282E (formation_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE public_formation (id INT AUTO_INCREMENT NOT NULL, titre VARCHAR(255) NOT NULL, ordre INT NOT NULL, formation_id INT NOT NULL, INDEX IDX_6D67FA3E5200282E (formation_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE thematique_formation (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, slug VARCHAR(20) NOT NULL, domaine_id INT NOT NULL, INDEX IDX_A361A3054272FC9F (domaine_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
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
        $this->addSql('DROP TABLE avis_formation');
        $this->addSql('DROP TABLE chapitre_module_formation');
        $this->addSql('DROP TABLE domaine_formation');
        $this->addSql('DROP TABLE formation');
        $this->addSql('DROP TABLE formation_thematique_formation');
        $this->addSql('DROP TABLE inscription_inter_formation');
        $this->addSql('DROP TABLE module_formation');
        $this->addSql('DROP TABLE niveau_formation');
        $this->addSql('DROP TABLE objectif_formation');
        $this->addSql('DROP TABLE prerequis_formation');
        $this->addSql('DROP TABLE public_formation');
        $this->addSql('DROP TABLE thematique_formation');
        $this->addSql('DROP TABLE user');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
