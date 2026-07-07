<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\Report;
use App\Entity\Review;
use App\Form\CommentType;
use App\Form\ReportType;
use App\Form\ReviewType;
use App\Repository\CourseRepository;
use App\Repository\ReviewRepository;
use App\Repository\UserRepository;
use App\Security\Voter\ReviewVoter;
use App\Service\EmailService;
use App\Service\ExternalModerationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/review')]
final class ReviewController extends AbstractController
{
    #[Route(name: 'app_review_index', methods: ['GET'])]
    public function index(ReviewRepository $reviewRepository): Response
    {
        return $this->render('review/index.html.twig', [
            'reviews' => $reviewRepository->findPublic(),
            'myReviews' => $this->getUser() ? $reviewRepository->findBy(['user' => $this->getUser()], ['createdAt' => 'DESC']) : [],
        ]);
    }

    #[Route('/new', name: 'app_review_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function new(Request $request, EntityManagerInterface $entityManager, EmailService $emailService, UserRepository $userRepository, ExternalModerationService $moderation, CourseRepository $courseRepository): Response
    {
        $review = new Review();


        if (!$request->isMethod('POST')) {
            $course = $courseRepository->find($request->query->getInt('course'));
            if ($course !== null) {
                $review->setCourse($course);
            }
        }

        $form = $this->createForm(ReviewType::class, $review);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $review->setUser($this->getUser());
            $review->setStatus(Review::STATUS_APPROVED);

            // Automated content moderation (external API or local fallback).
            $result = $moderation->analyze((string) $review->getContent(), $this->getUser());
            if ($result->isAggressive()) {
                $review->setStatus(Review::STATUS_REJECTED);
            }

            $entityManager->persist($review);
            $entityManager->flush();

            $emailService->sendTemplate(
                $review->getUser()->getEmail(),
                'Votre avis a bien été déposé',
                'emails/review_deposited.html.twig',
                ['review' => $review],
                'review_deposited',
                $review->getUser(),
            );

            if ($result->isAggressive()) {
                $this->addFlash('warning', sprintf(
                    'Votre avis contient un langage inapproprié et a été rejeté automatiquement.%s',
                    $result->suggestedRewrite ? ' Reformulation suggérée : « ' . $result->suggestedRewrite . ' »' : '',
                ));
            } else {
                $this->addFlash('success', 'Votre avis a été publié avec succès.');
            }

            return $this->redirectToRoute('app_review_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('review/new.html.twig', [
            'review' => $review,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_review_show', methods: ['GET'])]
    public function show(Review $review): Response
    {
        $reportForm  = $this->createForm(ReportType::class, new Report());
        $commentForm = $this->createForm(CommentType::class, new Comment());

        return $this->render('review/show.html.twig', [
            'review'      => $review,
            'reportForm'  => $reportForm,
            'commentForm' => $commentForm,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_review_edit', methods: ['GET', 'POST'])]
    #[IsGranted(ReviewVoter::EDIT, subject: 'review')]
    public function edit(Request $request, Review $review, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ReviewType::class, $review);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_review_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('review/edit.html.twig', [
            'review' => $review,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_review_delete', methods: ['POST'])]
    #[IsGranted(ReviewVoter::DELETE, subject: 'review')]
    public function delete(Request $request, Review $review, EntityManagerInterface $entityManager, ReviewRepository $reviewRepository): Response
    {
        if ($this->isCsrfTokenValid('delete' . $review->getId(), $request->getPayload()->getString('_token'))) {
            $reviewRepository->detachAuditReferencesBeforeDelete($review);
            $entityManager->remove($review);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_review_index', [], Response::HTTP_SEE_OTHER);
    }
}
