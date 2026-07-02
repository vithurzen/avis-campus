<?php

namespace App\Controller;

use App\Entity\Review;
use App\Repository\ReviewRepository;
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
}
