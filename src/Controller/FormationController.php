<?php

namespace App\Controller;

use App\Repository\DomaineFormationRepository;
use App\Repository\FormationRepository;
use App\Service\SmartOfApiService;
use Psr\Cache\InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

#[Route('/formation', name: 'app_formation')]
class FormationController extends AbstractController
{

    private FormationRepository $formationRepository;
    private DomaineFormationRepository $domaineFormationRepository;

    public function __construct(FormationRepository        $formationRepository,
                                DomaineFormationRepository $domaineFormationRepository)
    {
        $this->formationRepository = $formationRepository;
        $this->domaineFormationRepository = $domaineFormationRepository;
    }

    /**
     * @throws TransportExceptionInterface
     * @throws InvalidArgumentException
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     */
    #[Route('/', name: '')]
    public function index(SmartOfApiService $smartOfApiService): Response
    {

        // Récupération des formations à mettre en page d'accueil
        $response = $smartOfApiService->callSmartofApi('/api/produit/list');
        $formations = $response['produits'] ?? [];

        // Toutes les formations triées par catégorie
        $formationsParCategorie = [];

        foreach ($formations as $formation) {

            // Ignore archivées
            if (($formation['archived'] ?? false) === true) {
                continue;
            }

            $categorie = trim((string)($formation['custom_fields']['custom_field_4'] ?? ''));
            $sousCategorie = trim((string)($formation['custom_fields']['custom_field_6'] ?? ''));

            if ($categorie === '') {
                continue;
            }

            // Si pas de sous-catégorie, on met "Autres"
            if ($sousCategorie === '') {
                $sousCategorie = 'Autres';
            }

            $formationsParCategorie[$categorie][$sousCategorie][] = $formation;
        }

        // Tri des catégories
        ksort($formationsParCategorie, SORT_NATURAL | SORT_FLAG_CASE);

        // Tri des sous-catégories
        foreach ($formationsParCategorie as &$sousCategories) {
            ksort($sousCategories, SORT_NATURAL | SORT_FLAG_CASE);
        }
        unset($sousCategories);
        
        return $this->render('front/formation/index.html.twig', [
            'formationsParCategorie' => $formationsParCategorie
        ]);
    }

    #[Route('/details/{slug}', name: '_voir')]
    public function voir(string $slug): Response
    {
        $formation = $this->formationRepository->findOneBy(['slug' => $slug]);
        if ($formation == null) {
            return $this->redirectToRoute('app_main');
        }

        return $this->render('front/formation/voir.html.twig', [
            'formation' => $formation
        ]);
    }

    #[Route('/inscription/{slug}', name: '_inscription')]
    public function inscription(string $slug): Response
    {
        $formation = $this->formationRepository->findOneBy(['slug' => $slug]);
        if ($formation == null) {
            return $this->redirectToRoute('app_main');
        }

        return $this->render('front/formation/inscription.html.twig', [
            'formation' => $formation
        ]);

    }
}
