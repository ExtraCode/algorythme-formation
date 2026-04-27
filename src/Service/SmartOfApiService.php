<?php
namespace App\Service;

use App\Entity\SmartOfAuth;
use App\Repository\SmartOfAuthRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\InvalidArgumentException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/*
 * Doc : https://europe-west3-algorythme-formation-mobileo.cloudfunctions.net/docs/swagger/
 * Mail : support@smartof.tech
 */
class SmartOfApiService
{
    private const string CACHE_KEY = 'smartof_id_token';

    public function __construct(
        private HttpClientInterface $httpClient,
        private CacheInterface $cache,
        private SmartOfAuthRepository $smartofAuthRepository,
        private EntityManagerInterface $entityManager,
        private string $smartofEmail,
        private string $smartofPassword,
        private string $smartofApiKey,
    ) {
    }

    /**
     * @throws InvalidArgumentException
     * Récupère un token pour SmartOF
     */
    public function getToken(): string
    {
        return $this->cache->get(self::CACHE_KEY, function (ItemInterface $item) {

            // Pas de token en cache, on prend le refresh token
            $auth = $this->smartofAuthRepository->findOneBy([]);

            // Si on ne trouve rien, on fait un login pour avoir un token
            if (!$auth || !$auth->getRefreshToken()) {
                $data = $this->loginWithPassword();
            } else {
                // Sinon, on recharge un token grâce au refresh token
                $data = $this->refreshToken($auth->getRefreshToken());
            }

            // Ici, on a un token
            $idToken = $data['idToken'] ?? $data['access_token'] ?? null;

            if (!$idToken) {
                throw new \RuntimeException('Token Smartof introuvable dans la réponse.');
            }

            // SI on a un nouveau refresh token, on le stock
            if (isset($data['refreshToken']) || isset($data['refresh_token'])) {
                $newRefreshToken = $data['refreshToken'] ?? $data['refresh_token'];

                if (!$auth) {
                    $auth = new SmartOfAuth();
                }

                $auth->setRefreshToken($newRefreshToken);

                $this->entityManager->persist($auth);
                $this->entityManager->flush();
            }

            $expiresIn = (int) ($data['expiresIn'] ?? $data['expires_in'] ?? 3600);

            $item->expiresAfter(max($expiresIn - 60, 300));

            return $idToken;
        });
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     * Récupère un token pour SmartOF à partir de l'email et du mot de passe'
     */
    private function loginWithPassword(): array
    {
        $response = $this->httpClient->request('POST', 'https://identitytoolkit.googleapis.com/v1/accounts:signInWithPassword', [
            'query' => [
                'key' => $this->smartofApiKey,
            ],
            'json' => [
                'email' => $this->smartofEmail,
                'password' => $this->smartofPassword,
                'returnSecureToken' => true,
            ],
        ]);

        return $response->toArray();
    }


    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     * Récupère un token SmartOF grâce au refresh token
     */
    private function refreshToken(string $refreshToken): array
    {
        $response = $this->httpClient->request('POST', 'https://securetoken.googleapis.com/v1/token', [
            'query' => [
                'key' => $this->smartofApiKey,
            ],
            'body' => [
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
            ],
        ]);

        return $response->toArray();
    }

    /**
     * @throws TransportExceptionInterface
     * @throws InvalidArgumentException
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     * Appelle un endpoint SmartOF
     */
    public function callSmartofApi(string $url): array
    {
        $token = $this->getToken();
        $baseUrl = 'https://europe-west3-algorythme-formation-mobileo.cloudfunctions.net/external';

        $response = $this->httpClient->request('POST', $baseUrl.$url, [
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer '.$token,
            ],
        ]);

        return $response->toArray();
    }
}
