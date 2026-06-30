<?php

namespace App\Service;

use App\Entity\ApiLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Checks user-generated content against an external moderation API and logs
 * every call in an ApiLog. When no endpoint is configured it is a safe no-op
 * (content is allowed).
 */
class ExternalModerationService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private EntityManagerInterface $entityManager,
        #[Autowire(env: 'EXTERNAL_MODERATION_URL')]
        private string $endpoint = '',
    ) {
    }

    /**
     * Returns true when the content is acceptable (or no external service is
     * configured / reachable — we fail open so outages never block reviews).
     */
    public function check(string $content, ?User $user = null): bool
    {
        if ('' === $this->endpoint) {
            return true;
        }

        $status = 0;
        $flagged = false;

        try {
            $response = $this->httpClient->request('POST', $this->endpoint, [
                'json' => ['content' => $content],
            ]);
            $status = $response->getStatusCode();
            $data = $response->toArray(false);
            $flagged = (bool) ($data['flagged'] ?? false);
        } catch (ExceptionInterface) {
            // network/decoding failure — fail open
            $flagged = false;
        } finally {
            $this->log($user, $status);
        }

        return !$flagged;
    }

    private function log(?User $user, int $status): void
    {
        $log = (new ApiLog())
            ->setUser($user)
            ->setEndpoint($this->endpoint)
            ->setRequestType('POST')
            ->setResponseStatus($status);

        $this->entityManager->persist($log);
        $this->entityManager->flush();
    }
}
