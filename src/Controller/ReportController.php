<?php

namespace App\Controller;

use App\Entity\Report;
use App\Form\ReportType;
use App\Repository\ReviewRepository;
use App\Repository\UserRepository;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/report')]
final class ReportController extends AbstractController
{
    #[Route('/new', name: 'app_report_new', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        ReviewRepository $reviewRepository,
        UserRepository $userRepository,
        EmailService $emailService,
    ): Response {
        $report = new Report();
        $form   = $this->createForm(ReportType::class, $report);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $reviewId = (int) $form->get('reviewId')->getData();
            $review   = $reviewId ? $reviewRepository->find($reviewId) : null;

            $report
                ->setUser($this->getUser())
                ->setReview($review)
                ->setStatus(Report::STATUS_OPEN)
                ->setCreatedAt(new \DateTimeImmutable());

            $em->persist($report);
            $em->flush();

            $emailService->sendTemplateToMany(
                $userRepository->findModerators(),
                'Nouveau signalement',
                'emails/report_submitted.html.twig',
                ['report' => $report],
                'report_submitted',
            );

            if ($review !== null && $review->getUser() !== null) {
                $emailService->sendTemplate(
                    $review->getUser()->getEmail(),
                    'Votre avis a été signalé',
                    'emails/review_reported.html.twig',
                    ['review' => $review],
                    'review_reported',
                    $review->getUser(),
                );
            }

            $this->addFlash('success', 'Votre signalement a bien été envoyé. Merci !');

            if ($review) {
                return $this->redirectToRoute('app_review_show', ['id' => $review->getId()], Response::HTTP_SEE_OTHER);
            }
        }

        return $this->redirectToRoute('app_review_index', [], Response::HTTP_SEE_OTHER);
    }
}
