<?php

namespace App\Controller\Api;

use App\Entity\Course;
use App\Entity\Review;
use App\Repository\CourseRepository;
use App\Repository\ReviewRepository;
use App\Service\ApiLoggerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

#[Route('/courses', name: 'courses_')]
class CourseController extends AbstractController
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

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request, CourseRepository $courseRepository): JsonResponse
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(50, max(1, (int) $request->query->get('limit', 20)));

        $courses = $courseRepository->findAllWithRelations();

        $total = count($courses);
        $offset = ($page - 1) * $limit;
        $paginated = array_slice($courses, $offset, $limit);

        $data = $this->serializer->normalize($paginated, null, $this->context(['course:list']));

        $this->apiLogger->log($request, Response::HTTP_OK, $this->getUser());

        return new JsonResponse([
            'data' => $data,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'pages' => (int) ceil($total / $limit),
            ],
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Request $request, Course $course): JsonResponse
    {
        $data = $this->serializer->normalize($course, null, $this->context(['course:read']));

        $this->apiLogger->log($request, Response::HTTP_OK, $this->getUser());

        return new JsonResponse(['data' => $data]);
    }

    #[Route('/{id}/reviews', name: 'reviews', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function reviews(Request $request, Course $course, ReviewRepository $reviewRepository): JsonResponse
    {
        $status = $request->query->get('status', Review::STATUS_APPROVED);
        if (!in_array($status, [Review::STATUS_APPROVED, Review::STATUS_PENDING, 'all'], true)) {
            $status = Review::STATUS_APPROVED;
        }

        $criteria = $status === 'all'
            ? ['course' => $course]
            : ['course' => $course, 'status' => $status];

        $reviews = $reviewRepository->findBy($criteria, ['createdAt' => 'DESC']);

        $data = $this->serializer->normalize($reviews, null, $this->context(['review:list']));

        $this->apiLogger->log($request, Response::HTTP_OK, $this->getUser());

        return new JsonResponse([
            'data' => $data,
            'meta' => [
                'course_id' => $course->getId(),
                'course_title' => $course->getTitle(),
                'total' => count($reviews),
                'status_filter' => $status,
            ],
        ]);
    }

    #[Route('/top', name: 'top', methods: ['GET'])]
    public function topCourses(Request $request, CourseRepository $courseRepository): JsonResponse
    {
        $limit = min(50, max(1, (int) $request->query->get('limit', 10)));
        $courses = $courseRepository->findTopCourses($limit);

        $data = $this->serializer->normalize($courses, null, $this->context(['course:list']));

        $this->apiLogger->log($request, Response::HTTP_OK, $this->getUser());

        return new JsonResponse(['data' => $data]);
    }
}
