<?php

namespace App\Controller\Api;

use App\Entity\Formation;
use App\Entity\Review;
use App\Service\ApiLoggerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

#[Route('/formations', name: 'formations_')]
class FormationController extends AbstractController
{
    public function __construct(
        private readonly ApiLoggerService $apiLogger,
        private readonly NormalizerInterface $serializer,
    ) {}

    private function context(array $groups): array
    {
        return [
            'groups' => $groups,
            AbstractNormalizer::CIRCULAR_REFERENCE_HANDLER => fn(object $obj) => method_exists($obj, 'getId') ? $obj->getId() : null,
        ];
    }

    #[Route('/{id}/stats', name: 'stats', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function stats(Request $request, Formation $formation): JsonResponse
    {
        $totalCourses = 0;
        $totalReviews = 0;
        $totalScore = 0;
        $ratingCount = 0;
        $semesterStats = [];

        foreach ($formation->getSemesters() as $semester) {
            $semesterCourses = $semester->getCourses();
            $semesterCourseCount = $semesterCourses->count();
            $semesterReviewCount = 0;

            foreach ($semesterCourses as $course) {
                foreach ($course->getReviews() as $review) {
                    if ($review->getStatus() === Review::STATUS_APPROVED) {
                        $semesterReviewCount++;
                        $totalReviews++;
                        foreach ($review->getRatings() as $rating) {
                            $totalScore += $rating->getScore();
                            $ratingCount++;
                        }
                    }
                }
                $totalCourses++;
            }

            $semesterStats[] = [
                'id' => $semester->getId(),
                'name' => $semester->getName(),
                'number' => $semester->getNumber(),
                'courseCount' => $semesterCourseCount,
                'reviewCount' => $semesterReviewCount,
            ];
        }

        $formationData = $this->serializer->normalize($formation, null, $this->context(['formation:read']));

        $this->apiLogger->log($request, Response::HTTP_OK, $this->getUser());

        return new JsonResponse([
            'formation' => $formationData,
            'stats' => [
                'totalSemesters' => count($semesterStats),
                'totalCourses' => $totalCourses,
                'totalReviews' => $totalReviews,
                'averageRating' => $ratingCount > 0 ? round($totalScore / $ratingCount, 2) : 0.0,
                'semesters' => $semesterStats,
            ],
        ]);
    }
}
