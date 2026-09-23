<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Garde-fous de l'orientation, qui déclenche un appel facturé à l'API Claude.
 *
 * Deux limites complémentaires. Celle par visiteur empêche une même personne
 * de monopoliser le service ; c'est la limite globale qui plafonne réellement
 * la dépense, puisqu'une adresse IP se change en quelques secondes.
 */
class QuotaOrientation
{
    public function __construct(
        #[Target('orientation_visiteur')]
        private readonly RateLimiterFactoryInterface $visiteurLimiter,
        #[Target('orientation_globale')]
        private readonly RateLimiterFactoryInterface $globaleLimiter,
        private readonly RequestStack                $requestStack,
    )
    {
    }

    /**
     * Le service accepte-t-il encore des demandes ? Consulté par la page
     * d'accueil pour ne pas proposer un formulaire qui serait refusé.
     *
     * Consommer zéro jeton lit l'état du quota sans l'entamer. C'est bien le
     * nombre de jetons restants qu'il faut regarder : consommer zéro jeton
     * est toujours accepté, même quand le quota est à sec.
     */
    public function disponible(): bool
    {
        return $this->globaleLimiter->create()->consume(0)->getRemainingTokens() > 0;
    }

    /**
     * Décompte une analyse. Retourne le message à afficher au visiteur quand
     * une des deux limites est atteinte, null quand la demande peut passer.
     *
     * Le quota global est consommé en premier : il tient même face à
     * quelqu'un qui change d'adresse IP.
     */
    public function consommer(): ?string
    {
        if (!$this->globaleLimiter->create()->consume()->isAccepted()) {
            return 'Le service est très sollicité en ce moment. Réessayez un peu plus tard, '
                . 'ou exposez-nous votre besoin directement : nous vous répondons sous cinq jours ouvrés.';
        }

        if (!$this->visiteurLimiter->create($this->identifiantVisiteur())->consume()->isAccepted()) {
            return 'Vous avez lancé plusieurs analyses coup sur coup. Laissez passer un moment, '
                . 'ou parlons-en de vive voix : un entretien de trente minutes ira plus vite.';
        }

        return null;
    }

    /**
     * Identifiant du visiteur pour la limitation.
     *
     * En IPv6, un seul abonné dispose de milliards d'adresses : compter par
     * adresse exacte ne limiterait rien. On retient donc le préfixe /64, qui
     * correspond au réseau qui lui est attribué.
     */
    private function identifiantVisiteur(): string
    {
        $requete = $this->requestStack->getCurrentRequest();
        $ip = $requete instanceof Request ? (string)$requete->getClientIp() : '';

        if ($ip === '' || !str_contains($ip, ':')) {
            return $ip;
        }

        $binaire = @inet_pton($ip);

        if ($binaire === false) {
            return $ip;
        }

        return inet_ntop(substr($binaire, 0, 8) . str_repeat("\0", 8)) . '/64';
    }
}
