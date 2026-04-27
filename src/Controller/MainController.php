<?php

namespace App\Controller;

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

class MainController extends AbstractController
{

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws InvalidArgumentException
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     */
    #[Route('/main', name: 'app_main')]
    public function index(SmartOfApiService $smartOfApiService): Response
    {

        // Récupération des formations à mettre en page d'accueil
        $response = $smartOfApiService->callSmartofApi('/api/produit/list');
        $formations = $response['produits'] ?? [];

        // Uniquement les formations à mettre en page d'accueil
        $produitsFormation = array_values(array_filter($formations, static function (array $formation): bool {
            return !empty(trim((string)($formation['custom_fields']['custom_field_1'] ?? '')));
        }));

        return $this->render('front/index.html.twig', [
            'produitsFormation' => $produitsFormation
        ]);
    }

    #[Route('/qui-sommes-nous', name: 'app_qui_sommes_nous')]
    public function qui_sommes_nous(): Response
    {
        return $this->render('front/qui_sommes_nous.html.twig');
    }

    #[Route('/mentions-legales', name: 'app_mentions_legales')]
    public function mentions(): Response
    {
        return $this->render('front/mentions_legales.html.twig');
    }

    #[Route('/donnees-personnelles', name: 'app_donnees_personnelles')]
    public function donnees(): Response
    {
        return $this->render('front/donnees_personnelles.html.twig');
    }

    #[Route('/contact', name: 'app_contact')]
    public function contact(): Response
    {
        return $this->render('front/contact.html.twig');
    }
}
