<?php

namespace App\Controller;

use AllowDynamicProperties;
use App\Service\MailService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

#[AllowDynamicProperties]
class MainController extends AbstractController
{

    /**
     * Quiz de cadrage de la page d'accueil.
     *
     * Défini ici plutôt que dans le template : le contrôleur en a besoin
     * pour libeller et valider les réponses reçues côté serveur.
     */
    private const QUESTIONS_CADRAGE = [
        [
            'id' => 'domaine',
            'libelle' => 'Domaine prioritaire',
            'question' => 'Quel domaine vous intéresse ?',
            'options' => [
                'Intelligence artificielle',
                'Cybersécurité',
                'Data',
                'Développement web, logiciel ou mobile',
                'Systèmes et réseaux',
                'Je ne sais pas encore',
            ],
        ],
        [
            'id' => 'niveau',
            'libelle' => 'Point de départ des équipes',
            'question' => 'Où en sont vos équipes ?',
            'options' => [
                'Elles utilisent déjà ces outils, sans cadre',
                'Elles débutent',
                'Les niveaux sont très hétérogènes',
                'Je ne sais pas précisément',
            ],
        ],
        [
            'id' => 'effectif',
            'libelle' => 'Personnes à former',
            'question' => 'Combien de personnes seraient à former ?',
            'options' => [
                '1 ou 2',
                '3 à 10',
                '11 à 30',
                'Plus de 30',
                'Je ne sais pas encore',
            ],
        ],
        [
            'id' => 'taille',
            'libelle' => "Taille de l'entreprise",
            'question' => 'Quelle est la taille de votre entreprise ?',
            'options' => [
                'Moins de 20 salariés',
                '20 à 50 salariés',
                '50 à 250 salariés',
                'Plus de 250 salariés',
            ],
        ],
    ];

    #[Route('/main', name: 'app_main')]
    public function index(): Response
    {
        return $this->render('front/accueil.html.twig', [
            'questions' => self::QUESTIONS_CADRAGE,
        ]);
    }

    /**
     * Demande de rappel envoyée par le quiz de cadrage (fetch, réponse JSON).
     */
    #[Route('/demande-de-rappel', name: 'app_demande_rappel', methods: ['POST'])]
    public function demandeRappel(Request $request, MailService $mailService): JsonResponse
    {
        if (!$this->isCsrfTokenValid('rappel', (string)$request->request->get('_csrf_token'))) {
            return new JsonResponse(
                ['message' => 'Votre session a expiré. Rechargez la page et réessayez.'],
                Response::HTTP_FORBIDDEN
            );
        }

        $contact = [
            'nom' => trim((string)$request->request->get('nom')),
            'poste' => trim((string)$request->request->get('poste')),
            'email' => trim((string)$request->request->get('email')),
            'tel' => trim((string)$request->request->get('tel')),
        ];

        $erreurs = $this->validerDemandeRappel($contact);

        if ($erreurs) {
            return new JsonResponse(['errors' => $erreurs], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $mailService->sendDemandeRappel($contact, $this->libellerReponses($request));
        } catch (Throwable $e) {
            return new JsonResponse(
                ['message' => "L'envoi a échoué. Appelez-nous au 05 54 54 24 84, nous prenons le relais."],
                Response::HTTP_BAD_GATEWAY
            );
        }

        return new JsonResponse([
            'message' => 'Merci. Vos réponses et vos coordonnées nous sont transmises. '
                . "Nous vous rappelons pour fixer l'appel de cadrage de 30 minutes.",
        ]);
    }

    /**
     * @param array<string, string> $contact
     *
     * @return array<string, string> champ en erreur => message affiché sous le champ
     */
    private function validerDemandeRappel(array $contact): array
    {
        $erreurs = [];

        if ($contact['nom'] === '') {
            $erreurs['nom'] = "Indiquez votre nom pour qu'on sache qui rappeler.";
        }

        if ($contact['poste'] === '') {
            $erreurs['poste'] = 'Indiquez votre poste.';
        }

        if (!filter_var($contact['email'], FILTER_VALIDATE_EMAIL)) {
            $erreurs['email'] = 'Indiquez un email valide pour recevoir la confirmation.';
        }

        if (strlen(preg_replace('/\D/', '', $contact['tel'])) < 10) {
            $erreurs['tel'] = 'Indiquez un numéro à 10 chiffres pour être rappelé.';
        }

        return $erreurs;
    }

    /**
     * Ne retient que les réponses correspondant à une option connue :
     * le contenu du formulaire n'est pas repris tel quel dans l'email.
     *
     * @return array<string, string> libellé de la question => réponse choisie
     */
    private function libellerReponses(Request $request): array
    {
        $reponses = [];

        foreach (self::QUESTIONS_CADRAGE as $question) {
            $reponse = (string)$request->request->get($question['id'], '');

            if (in_array($reponse, $question['options'], true)) {
                $reponses[$question['libelle']] = $reponse;
            }
        }

        return $reponses;
    }

    function recupererFormations(): Response
    {
        // Récupération des formations à mettre en page d'accueil
//        $response = $smartOfApiService->callSmartofApi('/api/produit/list');
//        $formations = $response['produits'] ?? [];
//
//        // Uniquement les formations à mettre en page d'accueil
//        $produitsFormation = array_values(array_map(function (array $formation) {
//            $formation['slug'] = $this->slugger
//                ->slug($formation['meta']['nom'])
//                ->lower();
//
//            return $formation;
//        }, array_filter($formations, static function (array $formation): bool {
//            return !empty(trim((string)($formation['custom_fields']['custom_field_1'] ?? '')));
//        })));
//
//        return $this->render('front/index.html.twig', [
//            'produitsFormation' => $produitsFormation
//        ]);
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

}
