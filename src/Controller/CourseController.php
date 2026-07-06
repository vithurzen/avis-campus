<?php

namespace App\Controller;

use App\Entity\Course;
use App\Form\CourseType;
use App\Repository\CourseRepository;
use App\Repository\FavoriteRepository;
use App\Repository\FormationRepository;
use App\Repository\SemesterRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/course')]
final class CourseController extends AbstractController
{
    #[Route(name: 'app_course_index', methods: ['GET'])]
    public function index(CourseRepository $courseRepository, FormationRepository $formationRepository, FavoriteRepository $favoriteRepository): Response
    {
        $favoriteCourseIds = [];
        if ($this->getUser()) {
            foreach ($favoriteRepository->findByUser($this->getUser()) as $fav) {
                $favoriteCourseIds[] = $fav->getCourse()->getId();
            }
        }

        return $this->render('course/index.html.twig', [
            'courses'           => $courseRepository->findAllWithRelations(),
            'formations'        => $formationRepository->findBy([], ['name' => 'ASC']),
            'favoriteCourseIds' => $favoriteCourseIds,
        ]);
    }

    #[Route('/new', name: 'app_course_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function new(Request $request, EntityManagerInterface $entityManager, SemesterRepository $semesterRepository): Response
    {
        $course = new Course();

        $semester = null;
        if ($semesterId = $request->query->get('semester')) {
            $semester = $semesterRepository->find($semesterId);
            if ($semester) {
                $course->setSemester($semester);
            }
        }

        $form = $this->createForm(CourseType::class, $course, [
            'show_semester' => $semester === null,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($course);
            $entityManager->flush();

            if ($semester !== null) {
                $this->addFlash('success', 'Cours ajouté. Ajoutez-en un autre ou terminez.');
                return $this->redirectToRoute('app_course_new', ['semester' => $semester->getId()]);
            }

            return $this->redirectToRoute('app_course_show', ['id' => $course->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('course/new.html.twig', [
            'course' => $course,
            'form' => $form,
            'semester' => $semester,
        ]);
    }

    #[Route('/{id}', name: 'app_course_show', methods: ['GET'])]
    public function show(Course $course, Request $request, FavoriteRepository $favoriteRepository): Response
    {
        $fromFormation = $request->query->get('from') === 'formation';
        $isFavorited = $this->getUser()
            && $favoriteRepository->findOneByUserAndCourse($this->getUser(), $course) !== null;

        return $this->render('course/show.html.twig', [
            'course'        => $course,
            'fromFormation' => $fromFormation,
            'isFavorited'   => $isFavorited,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_course_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(Request $request, Course $course, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(CourseType::class, $course);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_course_show', ['id' => $course->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('course/edit.html.twig', [
            'course' => $course,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_course_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(Request $request, Course $course, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$course->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($course);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_course_index', [], Response::HTTP_SEE_OTHER);
    }
}
