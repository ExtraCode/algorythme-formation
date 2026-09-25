<?php

namespace App\Controller;

use App\Entity\Requete;
use App\Service\ConseillerFormations;
use App\Service\EmpreinteVisiteur;
use App\Service\QuotaOrientation;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

/**
 * Orientation depuis la page d'accueil : le visiteur décrit son besoin, la
 * page de résultats lui présente les formations du catalogue qui s'en
 * approchent le plus.
 *
 * Le parcours suit le schéma POST → redirection → GET. Deux raisons : une
 * analyse coûte un appel facturé à l'API Claude, et recharger la page ne
 * doit pas le relancer ; et Turbo, qui intercepte les envois de formulaire,
 * refuse d'afficher une réponse 200 à un POST - il attend une redirection.
 */
class OrientationController extends AbstractController
{
    /** Ce que le visiteur a saisi est réaffiché : il doit pouvoir le corriger. */
    private const int LONGUEUR_MAX_BESOIN = 2000;

    /** Le résultat voyage en session, le temps de la redirection. */
    private const string CLE_SESSION = 'orientation_resultat';

    /**
     * Le dernier besoin exprimé, conservé le temps de la visite : la page
     * d'accueil le remet dans le champ pour qu'il soit reformulé plutôt que
     * retapé. Lu par MainController.
     */
    public const string CLE_BESOIN = 'orientation_besoin';

    /**
     * Message porté jusqu'au catalogue quand aucune formation ne correspond.
     * Sa valeur est le besoin exprimé, pour que le visiteur puisse le
     * reformuler sans tout retaper.
     */
    private const string FLASH_SANS_RESULTAT = 'orientation_sans_resultat';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly EmpreinteVisiteur      $empreinte,
    )
    {
    }

    #[Route('/trouver-ma-formation', name: 'app_orientation', methods: ['POST'])]
    public function trouver(
        Request              $request,
        ConseillerFormations $conseiller,
        QuotaOrientation     $quota,
        LoggerInterface      $logger,
    ): Response
    {
        $besoin = mb_substr(trim((string)$request->request->get('besoin')), 0, self::LONGUEUR_MAX_BESOIN);

        if ($besoin === '') {
            return $this->redirectToRoute('home');
        }

        $request->getSession()->set(self::CLE_BESOIN, $besoin);

        // Le jeton doit être déclaré dans `stateless_token_ids` : sans cela il
        // est refusé, et le visiteur retombe sur la page d'accueil sans rien
        // comprendre. D'où le message, plutôt qu'une redirection muette.
        if (!$this->isCsrfTokenValid('orientation', (string)$request->request->get('_csrf_token'))) {
            $logger->warning('Orientation : jeton CSRF refusé.');

            return $this->resultat($request, $besoin, [], "Votre session a expiré avant l'envoi. "
                . 'Revenez à l\'accueil et décrivez à nouveau votre besoin.');
        }

        if ($message = $quota->consommer()) {
            return $this->resultat($request, $besoin, [], $message);
        }

        try {
            $recommandations = $conseiller->conseiller($besoin);

            $this->journaliser($logger, $request, $besoin, array_map(
                static fn(array $recommandation): string => (string)$recommandation['formation']['nom'],
                $recommandations,
            ));

            // Aucune correspondance : plutôt qu'une page de résultats vide, on
            // envoie le visiteur voir le catalogue, en lui disant pourquoi.
            if (!$recommandations) {
                $this->addFlash(self::FLASH_SANS_RESULTAT, $besoin);

                return $this->redirectToRoute('app_formation', status: Response::HTTP_SEE_OTHER);
            }

            return $this->resultat($request, $besoin, $recommandations);
        } catch (Throwable $e) {
            // Le détail part dans les logs ; le visiteur, lui, a besoin
            // d'une porte de sortie, pas d'un message technique.
            $logger->error('Orientation : analyse impossible.', ['exception' => $e]);
            $this->journaliser($logger, $request, $besoin, null);

            return $this->resultat($request, $besoin, [], "L'analyse n'a pas abouti. Réessayez dans "
                . 'un instant, ou exposez-nous votre besoin directement : nous vous répondons sous '
                . 'cinq jours ouvrés.');
        }
    }

    /**
     * La page de résultats. Elle relit ce que le POST a déposé en session :
     * le visiteur peut donc la recharger ou revenir dessus sans relancer
     * d'analyse.
     */
    #[Route('/votre-besoin', name: 'app_orientation_resultat', methods: ['GET'])]
    public function voirResultat(Request $request): Response
    {
        $resultat = $request->getSession()->get(self::CLE_SESSION);

        if (!is_array($resultat)) {
            return $this->redirectToRoute('home');
        }

        return $this->render('front/orientation/resultat.html.twig', $resultat);
    }

    /**
     * Conserve le besoin, l'empreinte du visiteur et les formations retenues
     * pour l'admin.
     * Un échec d'écriture ne doit jamais priver le visiteur de son résultat :
     * il est journalisé, sans plus.
     *
     * @param list<string>|null $formations `null` quand l'analyse a échoué
     */
    private function journaliser(
        LoggerInterface $logger,
        Request         $request,
        string          $besoin,
        ?array          $formations,
    ): void
    {
        try {
            $this->em->persist(new Requete(
                $besoin,
                $this->empreinte->calculer($request->getClientIp()),
                $formations,
            ));
            $this->em->flush();
        } catch (Throwable $e) {
            $logger->error('Orientation : requête non enregistrée.', ['exception' => $e]);
        }
    }

    /**
     * Dépose le résultat en session et renvoie vers la page qui l'affiche.
     *
     * @param array<int, array{formation: array<string, mixed>, raison: string}> $recommandations
     */
    private function resultat(Request $request, string $besoin, array $recommandations, ?string $erreur = null): Response
    {
        $request->getSession()->set(self::CLE_SESSION, [
            'besoin' => $besoin,
            'recommandations' => $recommandations,
            'erreur' => $erreur,
        ]);

        return $this->redirectToRoute('app_orientation_resultat', status: Response::HTTP_SEE_OTHER);
    }
}
