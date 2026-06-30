<?php

namespace App\Service;

use App\Entity\ModerationAction;
use App\Entity\ModeratorProfile;
use App\Entity\Review;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Centralizes the review moderation workflow: status transition plus the side
 * effects (audit log, notification, email), delegating the notification and
 * email concerns to dedicated services.
 */
class ReviewModerationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private NotificationService $notificationService,
        private EmailService $emailService,
    ) {
    }

    public function approve(Review $review, User $moderator, ?string $reason = null): void
    {
        if (!$review->isPending()) {
            throw new \LogicException('Only a pending review can be approved.');
        }

        $this->applyDecision(
            $review,
            $moderator,
            Review::STATUS_APPROVED,
            'approve',
            $reason,
            'Votre avis a été approuvé',
            sprintf('Votre avis « %s » a été approuvé et est désormais visible publiquement.', $review->getTitle()),
            'review_approved',
            'emails/review_approved.html.twig',
        );
    }

    public function reject(Review $review, User $moderator, ?string $reason = null): void
    {
        if (!$review->isPending()) {
            throw new \LogicException('Only a pending review can be rejected.');
        }

        $this->applyDecision(
            $review,
            $moderator,
            Review::STATUS_REJECTED,
            'reject',
            $reason,
            'Votre avis a été rejeté',
            sprintf(
                'Votre avis « %s » a été rejeté.%s',
                $review->getTitle(),
                $reason ? ' Motif : ' . $reason : '',
            ),
            'review_rejected',
            'emails/review_rejected.html.twig',
        );
    }

    public function hide(Review $review, User $moderator, ?string $reason = null): void
    {
        if (!$review->isApproved()) {
            throw new \LogicException('Only an approved review can be hidden.');
        }

        $this->applyDecision(
            $review,
            $moderator,
            Review::STATUS_HIDDEN,
            'hide',
            $reason,
            'Votre avis a été masqué',
            sprintf(
                'Votre avis « %s » a été masqué par la modération.%s',
                $review->getTitle(),
                $reason ? ' Motif : ' . $reason : '',
            ),
            'review_hidden',
            'emails/review_hidden.html.twig',
        );
    }

    private function applyDecision(
        Review $review,
        User $moderator,
        string $newStatus,
        string $actionLabel,
        ?string $reason,
        string $notificationTitle,
        string $notificationMessage,
        string $emailType,
        string $emailTemplate,
    ): void {
        $profile = $moderator->getProfile();
        if (!$profile instanceof ModeratorProfile) {
            throw new \LogicException('The acting user is not a moderator.');
        }

        $author = $review->getUser();

        // 1. Status transition + audit trail
        $review->setStatus($newStatus);
        $action = (new ModerationAction())
            ->setModerator($profile)
            ->setReview($review)
            ->setAction($actionLabel)
            ->setReason($reason)
            ->setCreatedAt(new \DateTimeImmutable());
        $this->entityManager->persist($action);
        $this->entityManager->flush();

        // 2. Notify the author (in-app + email), delegated to dedicated services
        $this->notificationService->notify($author, $notificationTitle, $notificationMessage);
        $this->emailService->sendTemplate(
            $author->getEmail(),
            $notificationTitle,
            $emailTemplate,
            ['review' => $review, 'reason' => $reason],
            $emailType,
            $author,
        );
    }
}
