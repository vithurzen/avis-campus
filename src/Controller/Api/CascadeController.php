<?php

namespace App\Controller\Api;

use App\Entity\Course;
use App\Entity\Formation;
use App\Entity\Semester;
use App\Entity\User;
use App\Repository\CourseRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Endpoints JSON alimentant la cascade Formation → Semestre → Cours du
 * formulaire d'avis (repopulation dynamique des menus déroulants côté client).
 */
#[Route('/api/cascade')]
#[IsGranted('ROLE_USER')]
final class CascadeController extends AbstractController
{
    /**
     * Semestres d'une formation (triés par numéro).
     */
    #[Route('/formations/{id}/semesters', name: 'api_cascade_semesters', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function semesters(Formation $formation): JsonResponse
    {
        $data = [];
        foreach ($formation->getSemesters() as $semester) {
            $data[] = ['id' => $semester->getId(), 'name' => $semester->getName()];
        }

        return $this->json($data);
    }

    /**
     * Cours d'un semestre non encore reviewés par l'utilisateur courant.
     */
    #[Route('/semesters/{id}/courses', name: 'api_cascade_courses', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function courses(Semester $semester, CourseRepository $courseRepository): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $courses = $courseRepository
            ->findCoursesNotReviewedBy($user, null, $semester)
            ->getQuery()
            ->getResult();

        $data = array_map(
            static fn (Course $course): array => ['id' => $course->getId(), 'title' => $course->getTitle()],
            $courses,
        );

        return $this->json($data);
    }
}
