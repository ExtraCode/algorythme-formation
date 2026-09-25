<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Empreinte d'une adresse IP : la même adresse donne toujours la même
 * empreinte, ce qui suffit à repérer un visiteur qui fait plusieurs
 * recherches, sans conserver l'adresse elle-même.
 *
 * HMAC plutôt qu'un simple SHA-256 : l'espace des IPv4 est assez petit pour
 * être parcouru en entier, un hachage sans clé se retournerait en minutes.
 * La clé est le secret de l'application ; s'il change, les empreintes
 * repartent de zéro (les anciennes requêtes restent, mais ne se relient
 * plus aux nouvelles).
 */
class EmpreinteVisiteur
{
    public function __construct(
        #[Autowire('%kernel.secret%')] private readonly string $secret,
    )
    {
    }

    /** 64 caractères hexadécimaux, ou `null` sans adresse. */
    public function calculer(?string $ip): ?string
    {
        if ($ip === null || $ip === '') {
            return null;
        }

        return hash_hmac('sha256', $ip, $this->secret);
    }
}
