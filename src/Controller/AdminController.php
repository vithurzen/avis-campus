<?php

namespace App\Controller;

use App\Entity\Report;
use App\Entity\Review;
use App\Entity\Teacher;
use App\Repository\ReportRepository;
use App\Repository\ReviewRepository;
use App\Repository\TeacherRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
final class AdminController extends AbstractController
{
    #[Route('/users', name: 'app_admin_users', methods: ['GET'])]
    public function users(UserRepository $userRepository): Response
    {
        return $this->render('admin/users.html.twig', [
            'users' => $userRepository->findBy([], ['createdAt' => 'DESC']),
        ]);
    }

    #[Route('/reviews', name: 'app_admin_reviews', methods: ['GET'])]
    public function reviews(ReviewRepository $reviewRepository): Response
    {
        return $this->render('admin/reviews.html.twig', [
            'reviews' => $reviewRepository->findBy([], ['createdAt' => 'DESC']),
        ]);
    }

    #[Route('/reviews/{id}/hide', name: 'app_admin_review_hide', methods: ['POST'])]
    public function hideReview(Request $request, Review $review, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('admin_review_' . $review->getId(), $request->getPayload()->getString('_token'))) {
            $review->setStatus(Review::STATUS_HIDDEN);
            $em->flush();
            $this->addFlash('success', 'Avis masqué.');
        }

        return $this->redirectToRoute('app_admin_reviews', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/reviews/{id}/approve', name: 'app_admin_review_approve', methods: ['POST'])]
    public function approveReview(Request $request, Review $review, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('admin_review_' . $review->getId(), $request->getPayload()->getString('_token'))) {
            $review->setStatus(Review::STATUS_APPROVED);
            $em->flush();
            $this->addFlash('success', 'Avis restauré.');
        }

        return $this->redirectToRoute('app_admin_reviews', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/teachers', name: 'app_admin_teachers', methods: ['GET'])]
    public function teachers(TeacherRepository $teacherRepository): Response
    {
        return $this->render('admin/teachers.html.twig', [
            'teachers' => $teacherRepository->findBy([], ['lastName' => 'ASC']),
        ]);
    }

    #[Route('/teachers/new', name: 'app_admin_teacher_new', methods: ['POST'])]
    public function newTeacher(Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('new_teacher', $request->getPayload()->getString('_token'))) {
            $this->addFlash('error', 'Token invalide.');
            return $this->redirectToRoute('app_admin_teachers', [], Response::HTTP_SEE_OTHER);
        }

        $firstName = trim($request->getPayload()->getString('firstName'));
        $lastName  = trim($request->getPayload()->getString('lastName'));
        $email     = trim($request->getPayload()->getString('email')) ?: null;

        if (!$firstName || !$lastName) {
            $this->addFlash('error', 'Le prénom et le nom sont obligatoires.');
            return $this->redirectToRoute('app_admin_teachers', [], Response::HTTP_SEE_OTHER);
        }

        $teacher = (new Teacher())
            ->setFirstName($firstName)
            ->setLastName($lastName)
            ->setEmail($email)
            ->setCreatedAt(new \DateTimeImmutable());

        $em->persist($teacher);
        $em->flush();

        $this->addFlash('success', $firstName . ' ' . $lastName . ' a été ajouté(e).');

        return $this->redirectToRoute('app_admin_teachers', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/teachers/{id}/delete', name: 'app_admin_teacher_delete', methods: ['POST'])]
    public function deleteTeacher(Request $request, Teacher $teacher, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete_teacher_' . $teacher->getId(), $request->getPayload()->getString('_token'))) {
            $em->remove($teacher);
            $em->flush();
            $this->addFlash('success', 'Professeur supprimé.');
        }

        return $this->redirectToRoute('app_admin_teachers', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/reports', name: 'app_admin_reports', methods: ['GET'])]
    public function reports(ReportRepository $reportRepository): Response
    {
        return $this->render('admin/reports.html.twig', [
            'reports'   => $reportRepository->findAllOrdered(),
            'openCount' => $reportRepository->countOpen(),
        ]);
    }

    #[Route('/reports/{id}/resolve', name: 'app_admin_report_resolve', methods: ['POST'])]
    public function resolveReport(Request $request, Report $report, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('admin_report_' . $report->getId(), $request->getPayload()->getString('_token'))) {
            $report->setStatus(Report::STATUS_RESOLVED);

            if ($request->getPayload()->getBoolean('hide_content')) {
                if ($report->getReview()) {
                    $report->getReview()->setStatus(Review::STATUS_HIDDEN);
                }
            }

            $em->flush();
            $this->addFlash('success', 'Signalement traité.');
        }

        return $this->redirectToRoute('app_admin_reports', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/reports/{id}/dismiss', name: 'app_admin_report_dismiss', methods: ['POST'])]
    public function dismissReport(Request $request, Report $report, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('admin_report_' . $report->getId(), $request->getPayload()->getString('_token'))) {
            $report->setStatus(Report::STATUS_DISMISSED);
            $em->flush();
            $this->addFlash('success', 'Signalement ignoré.');
        }

        return $this->redirectToRoute('app_admin_reports', [], Response::HTTP_SEE_OTHER);
    }
}
