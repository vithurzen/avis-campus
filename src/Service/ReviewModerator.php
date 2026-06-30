<?php

namespace App\Service;

use App\Entity\EmailLog;
use App\Entity\ModerationAction;
use App\Entity\ModeratorProfile;
use App\Entity\Notification;
use App\Entity\Review;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * Centralizes the review moderation workflow: status transition + the side
 * effects that must happen atomically (moderation log, notification, email).
 */
class ReviewModerator
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MailerInterface $mailer,
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
    ): void {
        $profile = $moderator->getProfile();
        if (!$profile instanceof ModeratorProfile) {
            throw new \LogicException('The acting user is not a moderator.');
        }

        $now = new \DateTimeImmutable();
        $author = $review->getUser();

        $review->setStatus($newStatus);

        // 1. Audit trail
        $action = (new ModerationAction())
            ->setModerator($profile)
            ->setReview($review)
            ->setAction($actionLabel)
            ->setReason($reason)
            ->setCreatedAt($now);
        $this->entityManager->persist($action);

        // 2. In-app notification for the review author
        $notification = (new Notification())
            ->setUser($author)
            ->setTitle($notificationTitle)
            ->setMessage($notificationMessage);
        $this->entityManager->persist($notification);

        // 3. Email (logged regardless of transport outcome)
        $this->sendEmail($author->getEmail(), $notificationTitle, $notificationMessage, $emailType, $author, $now);

        $this->entityManager->flush();
    }

    private function sendEmail(
        string $recipient,
        string $subject,
        string $body,
        string $type,
        User $user,
        \DateTimeImmutable $now,
    ): void {
        $log = (new EmailLog())
            ->setUser($user)
            ->setRecipient($recipient)
            ->setSubject($subject)
            ->setType($type)
            ->setSentAt($now);

        try {
            $email = (new Email())
                ->to($recipient)
                ->subject($subject)
                ->text($body);
            $this->mailer->send($email);
            $log->setStatus('sent');
        } catch (TransportExceptionInterface) {
            $log->setStatus('failed');
        }

        $this->entityManager->persist($log);
    }
}
