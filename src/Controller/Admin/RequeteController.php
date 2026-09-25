<?php

namespace App\Controller\Admin;

use App\Repository\RequeteRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Les besoins saisis dans le champ d'orientation de la page d'accueil :
 * texte, visiteur (empreinte de son IP) et formations retenues.
 *
 * Chaque visiteur porte le nombre de recherches qu'il a faites ; un clic
 * sur lui filtre la liste sur ses seules recherches (`?visiteur=`).
 *
 * L'accès est déjà fermé par `access_control` (security.yaml) ; l'attribut
 * le rappelle ici, et tient même si la règle venait à changer.
 */
#[IsGranted('ROLE_ADMIN')]
class RequeteController extends AbstractController
{
    private const int PAR_PAGE = 50;

    #[Route('/admin', name: 'app_admin_requete', methods: ['GET'])]
    public function index(Request $request, RequeteRepository $requetes): Response
    {
        $page = max(1, $request->query->getInt('page', 1));

        // Une empreinte mal formée est ignorée : la liste complète s'affiche.
        $visiteur = $request->query->getString('visiteur');
        $visiteur = preg_match('/^[0-9a-f]{64}$/', $visiteur) ? $visiteur : null;

        $liste = $requetes->page($page, self::PAR_PAGE, $visiteur);
        $total = count($liste);

        $empreintes = [];
        foreach ($liste as $requete) {
            if ($requete->getEmpreinte() !== null) {
                $empreintes[] = $requete->getEmpreinte();
            }
        }

        return $this->render('admin/requete/index.html.twig', [
            'requetes' => $liste,
            'total' => $total,
            'page' => $page,
            'pages' => max(1, (int)ceil($total / self::PAR_PAGE)),
            'visiteur' => $visiteur,
            'recherchesParVisiteur' => $requetes->compterParEmpreinte($empreintes),
            'stats' => $requetes->statistiques(),
        ]);
    }
}
