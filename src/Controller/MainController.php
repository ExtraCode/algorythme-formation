<?php

namespace App\Controller;

use App\Repository\FormationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MainController extends AbstractController
{

    private FormationRepository $formationRepository;

    public function __construct(FormationRepository $formationRepository)
    {
        $this->formationRepository = $formationRepository;
    }

    #[Route('/main', name: 'app_main')]
    public function index(): Response
    {
        return $this->render('front/index.html.twig', [
            'formations' => $this->formationRepository->findBy(['auTop' => true])
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
