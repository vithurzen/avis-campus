<?php

namespace App\Controller\Api;

use App\Entity\Course;
use App\Repository\CourseRepository;
use App\Repository\ReviewRatingRepository;
use App\Repository\ReviewRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1')]
final class CourseApiController extends AbstractController
{
    /** Upper bound for the optional ?limit query parameter. */
    private const MAX_LIMIT = 100;

    public function __construct(
        private readonly CourseRepository $courses,
        private readonly ReviewRepository $reviews,
        private readonly ReviewRatingRepository $reviewRatings,
    ) {
    }

    #[Route('/courses', name: 'api_courses_index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $limit = $this->resolveLimit($request);
        $courses = $this->courses->findBy([], ['title' => 'ASC'], $limit);

        return $this->json($courses, JsonResponse::HTTP_OK, [], $this->context(['course:list']));
    }

    #[Route('/courses/{id}', name: 'api_courses_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Course $course): JsonResponse
    {
        return $this->json($course, JsonResponse::HTTP_OK, [], $this->context(['course:read']));
    }

    #[Route('/courses/{id}/reviews', name: 'api_courses_reviews', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function reviews(Course $course): JsonResponse
    {
        $reviews = $this->reviews->findApprovedByCourse($course);

        return $this->json($reviews, JsonResponse::HTTP_OK, [], $this->context(['review:read']));
    }

    #[Route('/courses/{id}/stats', name: 'api_courses_stats', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function stats(Course $course): JsonResponse
    {
        $average = $this->reviewRatings->getAverageScoreForCourse($course);

        $payload = [
            'course' => [
                'id' => $course->getId(),
                'title' => $course->getTitle(),
            ],
            'averageScore' => $average !== null ? round($average, 2) : null,
            'reviewCount' => count($this->reviews->findApprovedByCourse($course)),
            'criteria' => $this->reviewRatings->getAveragesByCriteriaForCourse($course),
        ];

        return $this->json($payload);
    }

    #[Route('/top-courses', name: 'api_top_courses', methods: ['GET'])]
    public function topCourses(Request $request): JsonResponse
    {
        $limit = $this->resolveLimit($request);

        $rows = array_map(
            static fn (array $row): array => [
                'course' => $row['course'],
                'avgScore' => round((float) $row['avgScore'], 2),
            ],
            $this->courses->findTopRatedCourses($limit),
        );

        return $this->json($rows, JsonResponse::HTTP_OK, [], $this->context(['course:list']));
    }

    /**
     * Serialization context: selected groups plus a circular-reference safety net.
     *
     * @param string[] $groups
     *
     * @return array<string, mixed>
     */
    private function context(array $groups): array
    {
        return [
            'groups' => $groups,
            'circular_reference_handler' => static fn (object $object): mixed => method_exists($object, 'getId') ? $object->getId() : null,
        ];
    }

    private function resolveLimit(Request $request): int
    {
        $limit = $request->query->getInt('limit', 10);

        return max(1, min($limit, self::MAX_LIMIT));
    }
}
