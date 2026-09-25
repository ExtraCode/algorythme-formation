<?php

namespace App\Controller;

use App\Service\MailService;
use App\Service\VerificationRobot;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

/**
 * Page de contact : coordonnées, ce qui se passe après une demande, et un
 * formulaire qui part par email dans la boîte du site.
 *
 * Le formulaire est un envoi classique. En cas d'erreur, la page est
 * réaffichée avec les valeurs saisies et un statut 422 : Turbo n'affiche
 * la réponse d'un POST que si elle n'est pas un succès. Quand l'envoi
 * aboutit, redirection vers la page, qui montre alors la confirmation.
 */
class ContactController extends AbstractController
{
    private const string FLASH_ENVOYE = 'contact_envoye';

    /** Longueurs maximales : au-delà, ce n'est plus un message de contact. */
    private const array LONGUEURS_MAX = [
        'nom' => 120,
        'poste' => 120,
        'entreprise' => 120,
        'tel' => 30,
        'email' => 180,
        'message' => 3000,
    ];

    #[Route('/contact', name: 'app_contact', methods: ['GET'])]
    public function afficher(Request $request, VerificationRobot $robot): Response
    {
        $envoye = (bool)$request->getSession()->getFlashBag()->get(self::FLASH_ENVOYE);

        return $this->page($robot, [], [], envoye: $envoye);
    }

    #[Route('/contact', name: 'app_contact_envoyer', methods: ['POST'])]
    public function envoyer(
        Request           $request,
        MailService       $mailService,
        VerificationRobot $robot,
        LoggerInterface   $logger,
    ): Response
    {
        $valeurs = $this->lire($request);

        if (!$this->isCsrfTokenValid('contact', (string)$request->request->get('_csrf_token'))) {
            $logger->warning('Contact : jeton CSRF refusé.');

            return $this->page($robot, $valeurs, [], "Votre session a expiré avant l'envoi. "
                . 'Vérifiez votre message et renvoyez-le.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $erreurs = $this->valider($valeurs);

        if ($robot->estConfigure()
            && !$robot->verifier((string)$request->request->get(VerificationRobot::CHAMP), $request->getClientIp())) {
            $erreurs['robot'] = 'Cochez la case « Je ne suis pas un robot ».';
        }

        if ($erreurs) {
            return $this->page($robot, $valeurs, $erreurs, statut: Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $mailService->sendContact($valeurs);
        } catch (Throwable $e) {
            $logger->error('Contact : envoi impossible.', ['exception' => $e]);

            return $this->page($robot, $valeurs, [], "L'envoi a échoué. Appelez-nous au 05 54 54 24 84 ou "
                . 'écrivez directement à contact@algorythme-formation.fr, nous prenons le relais.',
                Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $this->addFlash(self::FLASH_ENVOYE, '1');

        // L'ancre sert sans JavaScript. Avec Turbo, elle est perdue au suivi
        // de la redirection : c'est le contrôleur `reveler` du gabarit qui
        // ramène alors le visiteur sur la confirmation.
        return $this->redirectToRoute('app_contact', ['_fragment' => 'formulaire'], Response::HTTP_SEE_OTHER);
    }

    /**
     * @return array<string, string> champ => valeur saisie, nettoyée
     */
    private function lire(Request $request): array
    {
        $valeurs = [];

        foreach (self::LONGUEURS_MAX as $champ => $max) {
            $valeurs[$champ] = mb_substr(trim((string)$request->request->get($champ)), 0, $max);
        }

        $valeurs['consentement'] = $request->request->getBoolean('consentement') ? '1' : '';

        return $valeurs;
    }

    /**
     * @param array<string, string> $valeurs
     *
     * @return array<string, string> champ en erreur => message affiché sous le champ
     */
    private function valider(array $valeurs): array
    {
        $erreurs = [];

        if ($valeurs['nom'] === '') {
            $erreurs['nom'] = 'Indiquez votre nom pour que nous sachions à qui répondre.';
        }

        if ($valeurs['poste'] === '') {
            $erreurs['poste'] = 'Indiquez votre fonction.';
        }

        if ($valeurs['entreprise'] === '') {
            $erreurs['entreprise'] = 'Indiquez votre entreprise.';
        }

        if (strlen(preg_replace('/\D/', '', $valeurs['tel'])) < 10) {
            $erreurs['tel'] = 'Indiquez un numéro à 10 chiffres pour être rappelé.';
        }

        if (!filter_var($valeurs['email'], FILTER_VALIDATE_EMAIL)) {
            $erreurs['email'] = 'Indiquez un courriel valide pour recevoir notre réponse.';
        }

        if ($valeurs['consentement'] === '') {
            $erreurs['consentement'] = 'Votre accord est nécessaire pour que nous puissions vous répondre.';
        }

        return $erreurs;
    }

    /**
     * @param array<string, string> $valeurs
     * @param array<string, string> $erreurs
     */
    private function page(
        VerificationRobot $robot,
        array             $valeurs,
        array             $erreurs,
        ?string           $erreurGlobale = null,
        int               $statut = Response::HTTP_OK,
        bool              $envoye = false,
    ): Response
    {
        return $this->render('front/contact.html.twig', [
            'valeurs' => $valeurs,
            'erreurs' => $erreurs,
            'erreurGlobale' => $erreurGlobale,
            'envoye' => $envoye,
            'cleRobot' => $robot->estConfigure() ? $robot->cleSite() : null,
        ], new Response(status: $statut));
    }
}
