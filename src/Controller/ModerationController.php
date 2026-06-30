<?php

namespace App\Controller;

use App\Entity\Review;
use App\Repository\ReviewRepository;
use App\Service\ReviewModerationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/moderation')]
#[IsGranted('ROLE_MODERATOR')]
final class ModerationController extends AbstractController
{
    #[Route('', name: 'app_moderation_index', methods: ['GET'])]
    public function index(ReviewRepository $reviewRepository): Response
    {
        return $this->render('moderation/index.html.twig', [
            'reviews' => $reviewRepository->findPending(),
        ]);
    }

    #[Route('/review/{id}/approve', name: 'app_moderation_review_approve', methods: ['POST'])]
    public function approve(Request $request, Review $review, ReviewModerationService $reviewModerationService): Response
    {
        if ($this->isCsrfTokenValid('moderate' . $review->getId(), $request->getPayload()->getString('_token'))) {
            $reviewModerationService->approve($review, $this->getUser());
            $this->addFlash('success', 'Avis approuvé.');
        }

        return $this->redirectToRoute('app_moderation_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/review/{id}/reject', name: 'app_moderation_review_reject', methods: ['POST'])]
    public function reject(Request $request, Review $review, ReviewModerationService $reviewModerationService): Response
    {
        if ($this->isCsrfTokenValid('moderate' . $review->getId(), $request->getPayload()->getString('_token'))) {
            $reason = $request->getPayload()->getString('reason') ?: null;
            $reviewModerationService->reject($review, $this->getUser(), $reason);
            $this->addFlash('success', 'Avis rejeté.');
        }

        return $this->redirectToRoute('app_moderation_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/review/{id}/hide', name: 'app_moderation_review_hide', methods: ['POST'])]
    public function hide(Request $request, Review $review, ReviewModerationService $reviewModerationService): Response
    {
        if ($this->isCsrfTokenValid('moderate' . $review->getId(), $request->getPayload()->getString('_token'))) {
            $reason = $request->getPayload()->getString('reason') ?: null;
            $reviewModerationService->hide($review, $this->getUser(), $reason);
            $this->addFlash('success', 'Avis masqué.');
        }

        return $this->redirectToRoute('app_moderation_index', [], Response::HTTP_SEE_OTHER);
    }
}
