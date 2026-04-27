<?php

namespace App\Twig;

use AllowDynamicProperties;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

#[AllowDynamicProperties]
class FormationExtension extends AbstractExtension
{

    public function getFunctions(): array
    {
        return [
            new TwigFunction('getCouleurByCategorieFormation', [$this, 'getCouleurByCategorieFormation']),
            new TwigFunction('calculNbApprenants', [$this, 'calculNbApprenants']),
        ];
    }

    /*
     * @param int $base
     * @return int
     * Retourne le nombre d'apprenants pour une formation en fonction du mois
     */
    function calculNbApprenants(int $base = 1): int
    {
        $mois = (int)date('n');

        $multiplicateurs = [
            1 => 4,  // Janvier
            2 => 6,  // Février
            3 => 5,  // etc
            4 => 7,
            5 => 8,
            6 => 10,
            7 => 9,
            8 => 6,
            9 => 7,
            10 => 8,
            11 => 9,
            12 => 12,
        ];

        $multiplicateur = $multiplicateurs[$mois] ?? 1;

        return (int)round($base * $multiplicateur);
    }

    /*
     * @param string $nomCategorie
     * @return string
     * Retourne la couleur d'une catégorie de formation
     */
    public function getCouleurByCategorieFormation(string $nomCategorie): string
    {

        if ($nomCategorie == "Intelligence Artificielle") {
            return "#549bff";
        }
        if ($nomCategorie == "Big data et analytics") {
            return "#f1bf49";
        }

        return "grey";

    }

    /*
     * @return array
     * Retourne les conversations non lues des admins
     */
    public function conversationsAdminNonLues(Admin $admin): array
    {
        return $this->conversationRepository->findNonLuesByAdmin($admin);
    }

    /*
     * Retourne true si le formateur est auteur ou destinataire de conversation sur une formation
     */
    public function findConversationForFormationAndFormateur(Formation $formation, Formateur $formateur): ?Conversation
    {

        return $this->conversationRepository->findConversationForFormationAndFormateur($formation, $formateur);

    }

}
