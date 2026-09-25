<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

/**
 * Case « Je ne suis pas un robot » des formulaires publics (reCAPTCHA v2).
 *
 * Le navigateur affiche la case avec la clé de site ; le serveur confirme
 * ensuite le jeton reçu avec la clé secrète. Sans clés configurées, la
 * vérification est simplement absente : le formulaire reste utilisable en
 * développement, et la page ne montre pas de case.
 */
class VerificationRobot
{
    private const string URL_VERIFICATION = 'https://www.google.com/recaptcha/api/siteverify';

    /** Nom du champ que le widget ajoute au formulaire. */
    public const string CHAMP = 'g-recaptcha-response';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface     $logger,
        private readonly string              $cleSite,
        private readonly string              $cleSecrete,
    )
    {
    }

    public function estConfigure(): bool
    {
        return $this->cleSite !== '' && $this->cleSecrete !== '';
    }

    public function cleSite(): string
    {
        return $this->cleSite;
    }

    /**
     * Vrai si le jeton a été validé par Google. Un échec réseau vaut refus :
     * mieux vaut demander au visiteur de recocher que laisser passer.
     */
    public function verifier(string $jeton, ?string $ip): bool
    {
        if ($jeton === '') {
            return false;
        }

        try {
            $reponse = $this->httpClient->request('POST', self::URL_VERIFICATION, [
                'body' => [
                    'secret' => $this->cleSecrete,
                    'response' => $jeton,
                    'remoteip' => $ip,
                ],
                'timeout' => 5,
            ])->toArray();
        } catch (Throwable $e) {
            $this->logger->error('Vérification reCAPTCHA impossible.', ['exception' => $e]);

            return false;
        }

        if (!($reponse['success'] ?? false)) {
            $this->logger->info('reCAPTCHA refusé.', ['codes' => $reponse['error-codes'] ?? []]);
        }

        return (bool)($reponse['success'] ?? false);
    }
}
