<?php

namespace App\Controller;

use App\Service\CatalogueFormations;
use Psr\Cache\InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

#[Route('/nos-formations', name: 'app_formation')]
class FormationController extends AbstractController
{
    public function __construct(
        private readonly CatalogueFormations $catalogue,
    )
    {
    }

    /**
     * Catalogue : les formations publiées dans SmartOF, avec un filtre par
     * domaine (domaines triés par ordre alphabétique).
     *
     * @throws InvalidArgumentException
     */
    #[Route('', name: '')]
    public function index(): Response
    {
        return $this->render('front/formation/index.html.twig', [
            'domaines' => $this->catalogue->domaines(),
            'formations' => $this->catalogue->lister(),
        ]);
    }

    /**
     * Fiche d'une formation. Une formation non publiée dans SmartOF n'a pas
     * de page : son slug renvoie une 404.
     *
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws InvalidArgumentException
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     */
    #[Route('/{slug}', name: '_voir')]
    public function voir(string $slug): Response
    {
        $formation = $this->catalogue->fiche($slug);

        if ($formation === null) {
            throw $this->createNotFoundException(sprintf('Aucune formation publiée pour le slug « %s ».', $slug));
        }

        return $this->render('front/formation/voir.html.twig', [
            'formation' => $formation,
        ]);
    }
}
