<?php

namespace App\Service;

use App\Entity\ApiLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Analyzes user-generated content for moderation and logs every call in an
 * ApiLog. When an external endpoint is configured (EXTERNAL_MODERATION_URL) it
 * is queried over HTTP; otherwise a local keyword heuristic acts as a stand-in
 * "fake API" so the feature is fully usable offline during development.
 *
 * Expected external API contract:
 *   POST {endpoint}  body: {"content": "..."}
 *   200 response:    {"verdict": "safe|aggressive|needs_review",
 *                     "score": 0.0, "suggested_rewrite": "..."|null}
 */
class ExternalModerationService
{
    /** Words that trigger an automatic "aggressive" verdict. */
    private const AGGRESSIVE_WORDS = [
        'connard', 'connards', 'idiot', 'idiote', 'débile', 'crétin', 'imbécile',
        'ferme-la', 'ta gueule', 'salaud', 'enculé', 'pute', 'merde',
        'hate', 'asshole', 'idiot', 'stupid bastard', 'shut up',
    ];

    /** Words that flag content for human review ("needs_review"). */
    private const SUSPECT_WORDS = [
        'nul', 'nulle', 'stupide', 'déteste', 'horrible', 'lamentable',
        'incompétent', 'incompétente', 'arnaque', 'honteux',
        'useless', 'terrible', 'worst', 'hate it',
    ];

    public function __construct(
        private HttpClientInterface $httpClient,
        private EntityManagerInterface $entityManager,
        #[Autowire(env: 'EXTERNAL_MODERATION_URL')]
        private string $endpoint = '',
    ) {
    }

    /**
     * Analyzes content and returns a typed verdict (+ optional suggested rewrite).
     * Fails open: network/decoding errors yield a "safe" result so outages never
     * block reviews.
     */
    public function analyze(string $content, ?User $user = null): ModerationResult
    {
        if ('' === $this->endpoint) {
            $this->log($user, 'local', 'LOCAL', null);

            return $this->analyzeLocally($content);
        }

        $status = 0;

        try {
            $response = $this->httpClient->request('POST', $this->endpoint, [
                'json' => ['content' => $content],
            ]);
            $status = $response->getStatusCode();
            $data = $response->toArray(false);

            return $this->mapResponse($data);
        } catch (ExceptionInterface) {
            // network/decoding failure — fail open
            return ModerationResult::safe();
        } finally {
            $this->log($user, $this->endpoint, 'POST', $status);
        }
    }

    /**
     * Convenience boolean wrapper: true when the content is acceptable to keep
     * (i.e. not flagged as aggressive).
     */
    public function check(string $content, ?User $user = null): bool
    {
        return !$this->analyze($content, $user)->isAggressive();
    }

    /**
     * @param array<string, mixed> $data
     */
    private function mapResponse(array $data): ModerationResult
    {
        $score = (float) ($data['score'] ?? 0.0);
        $rewrite = isset($data['suggested_rewrite']) ? (string) $data['suggested_rewrite'] : null;

        if (isset($data['verdict'])) {
            return new ModerationResult(ModerationVerdict::fromApi((string) $data['verdict']), $score, $rewrite);
        }

        // Back-compat with a simpler {"flagged": bool} contract.
        $verdict = ($data['flagged'] ?? false) ? ModerationVerdict::Aggressive : ModerationVerdict::Safe;

        return new ModerationResult($verdict, $score, $rewrite);
    }

    /**
     * Local stand-in for the external API: a naive keyword heuristic.
     */
    private function analyzeLocally(string $content): ModerationResult
    {
        $haystack = mb_strtolower($content);

        if ($this->containsAny($haystack, self::AGGRESSIVE_WORDS)) {
            return new ModerationResult(
                ModerationVerdict::Aggressive,
                0.9,
                $this->mask($content, self::AGGRESSIVE_WORDS),
            );
        }

        if ($this->containsAny($haystack, self::SUSPECT_WORDS)) {
            return new ModerationResult(
                ModerationVerdict::NeedsReview,
                0.5,
                $this->mask($content, self::SUSPECT_WORDS),
            );
        }

        return new ModerationResult(ModerationVerdict::Safe, 0.0, null);
    }

    /**
     * @param string[] $words
     */
    private function containsAny(string $haystack, array $words): bool
    {
        foreach ($words as $word) {
            if (str_contains($haystack, $word)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns the content with offending words replaced, as a naive rewrite.
     *
     * @param string[] $words
     */
    private function mask(string $content, array $words): string
    {
        return str_ireplace($words, '***', $content);
    }

    private function log(?User $user, string $endpoint, string $requestType, ?int $status): void
    {
        $log = (new ApiLog())
            ->setUser($user)
            ->setEndpoint($endpoint)
            ->setRequestType($requestType)
            ->setResponseStatus($status);

        $this->entityManager->persist($log);
        $this->entityManager->flush();
    }
}
