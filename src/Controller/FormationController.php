<?php

namespace App\Controller;

use App\Repository\DomaineFormationRepository;
use App\Repository\FormationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

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

    #[Route('/', name: '')]
    public function index(): Response
    {
        return $this->render('front/formation/index.html.twig', [
            'domainesFormation' => $this->domaineFormationRepository->findDomaineFormationWithFormation()
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
