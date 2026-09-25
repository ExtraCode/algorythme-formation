<?php

namespace App\Entity;

use App\Repository\RequeteRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Un besoin saisi dans le champ d'orientation de la page d'accueil, avec les
 * formations que l'analyse a retenues. Consulté dans l'admin (/admin).
 *
 * Le visiteur n'est connu que par l'empreinte de son adresse IP (voir
 * EmpreinteVisiteur) : assez pour regrouper ses recherches, sans garder
 * l'adresse.
 */
#[ORM\Entity(repositoryClass: RequeteRepository::class)]
#[ORM\Index(name: 'IDX_REQUETE_CREE_LE', fields: ['creeLe'])]
#[ORM\Index(name: 'IDX_REQUETE_EMPREINTE', fields: ['empreinte'])]
class Requete
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT)]
    private string $besoin;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $empreinte;

    /**
     * Noms des formations retenues, dans l'ordre de la recommandation.
     * `null` : l'analyse a échoué ; liste vide : aucune formation ne convenait.
     *
     * @var list<string>|null
     */
    #[ORM\Column(nullable: true)]
    private ?array $formations;

    #[ORM\Column]
    private DateTimeImmutable $creeLe;

    /**
     * @param list<string>|null $formations
     */
    public function __construct(string $besoin, ?string $empreinte, ?array $formations)
    {
        $this->besoin = $besoin;
        $this->empreinte = $empreinte;
        $this->formations = $formations;
        $this->creeLe = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBesoin(): string
    {
        return $this->besoin;
    }

    public function getEmpreinte(): ?string
    {
        return $this->empreinte;
    }

    /** Les six premiers caractères : de quoi reconnaître un visiteur à l'œil. */
    public function getEmpreinteCourte(): ?string
    {
        return $this->empreinte === null ? null : substr($this->empreinte, 0, 6);
    }

    /**
     * Une teinte (0-359) tirée de l'empreinte : le même visiteur garde la
     * même couleur d'une ligne à l'autre.
     */
    public function getTeinte(): int
    {
        return $this->empreinte === null ? 0 : hexdec(substr($this->empreinte, 0, 4)) % 360;
    }

    /**
     * @return list<string>|null
     */
    public function getFormations(): ?array
    {
        return $this->formations;
    }

    public function getCreeLe(): DateTimeImmutable
    {
        return $this->creeLe;
    }
}
