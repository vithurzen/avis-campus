<?php

namespace App\Tests\Service;

use App\Entity\AdminProfile;
use App\Entity\ModerationAction;
use App\Entity\ModeratorProfile;
use App\Entity\Review;
use App\Entity\User;
use App\Service\EmailService;
use App\Service\NotificationService;
use App\Service\ReviewModerationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ReviewModerationServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private NotificationService&MockObject $notificationService;
    private EmailService&MockObject $emailService;
    private ReviewModerationService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->notificationService = $this->createMock(NotificationService::class);
        $this->emailService = $this->createMock(EmailService::class);

        $this->service = new ReviewModerationService(
            $this->entityManager,
            $this->notificationService,
            $this->emailService,
        );
    }

    private function createModerator(): User
    {
        $moderator = new User();
        $moderator->setEmail('moderator@example.com');
        $moderator->setProfile(new ModeratorProfile());

        return $moderator;
    }

    private function createAdmin(): User
    {
        $admin = new User();
        $admin->setEmail('admin@example.com');
        $admin->setProfile(new AdminProfile());

        return $admin;
    }

    private function createReview(string $status, string $title = 'Un super cours'): Review
    {
        $author = new User();
        $author->setEmail('author@example.com');

        $review = new Review();
        $review->setUser($author);
        $review->setTitle($title);
        $review->setContent('Contenu de test');
        $review->setStatus($status);

        return $review;
    }

    public function testApproveTransitionsPendingReviewToApproved(): void
    {
        $review = $this->createReview(Review::STATUS_PENDING);
        $moderator = $this->createModerator();

        $this->service->approve($review, $moderator);

        $this->assertTrue($review->isApproved());
    }

    public function testApprovePersistsAModerationActionAndFlushes(): void
    {
        $review = $this->createReview(Review::STATUS_PENDING);
        $moderator = $this->createModerator();

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(function ($action) use ($review, $moderator) {
                return $action instanceof ModerationAction
                    && $action->getReview() === $review
                    && $action->getModerator() === $moderator->getProfile()
                    && $action->getAction() === 'approve'
                    && $action->getReason() === null;
            }));
        $this->entityManager->expects($this->once())->method('flush');

        $this->service->approve($review, $moderator);
    }

    public function testApproveNotifiesAndEmailsTheAuthor(): void
    {
        $review = $this->createReview(Review::STATUS_PENDING, 'Mon avis');
        $moderator = $this->createModerator();
        $author = $review->getUser();

        $this->notificationService->expects($this->once())
            ->method('notify')
            ->with($author, 'Votre avis a été approuvé', $this->stringContains('Mon avis'));

        $this->emailService->expects($this->once())
            ->method('sendTemplate')
            ->with(
                $author->getEmail(),
                'Votre avis a été approuvé',
                'emails/review_approved.html.twig',
                ['review' => $review, 'reason' => null],
                'review_approved',
                $author,
            );

        $this->service->approve($review, $moderator);
    }

    public function testApproveThrowsWhenReviewIsNotPending(): void
    {
        $review = $this->createReview(Review::STATUS_APPROVED);
        $moderator = $this->createModerator();

        $this->entityManager->expects($this->never())->method('persist');
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Only a pending review can be approved.');

        $this->service->approve($review, $moderator);
    }

    public function testApproveByAdminAlsoRecordsAuditTrail(): void
    {
        // Admins may also approve (per ReviewVoter), and Profile is single-table
        // inherited so an AdminProfile fits the ModerationAction relation too.
        $review = $this->createReview(Review::STATUS_PENDING);
        $admin = $this->createAdmin();

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(fn($action) => $action instanceof ModerationAction
                && $action->getModerator() === $admin->getProfile()
                && $action->getAction() === 'approve'));
        $this->entityManager->expects($this->once())->method('flush');

        $this->service->approve($review, $admin);

        $this->assertTrue($review->isApproved());
    }

    public function testApproveByActorWithoutAnyProfileSkipsAuditTrail(): void
    {
        $review = $this->createReview(Review::STATUS_PENDING);
        $noProfile = new User();
        $noProfile->setEmail('ghost@example.com');

        $this->entityManager->expects($this->never())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $this->service->approve($review, $noProfile);

        $this->assertTrue($review->isApproved());
    }

    public function testRejectTransitionsPendingReviewToRejected(): void
    {
        $review = $this->createReview(Review::STATUS_PENDING);
        $moderator = $this->createModerator();

        $this->service->reject($review, $moderator, 'Contenu inapproprié');

        $this->assertTrue($review->isRejected());
    }

    public function testRejectIncludesReasonInNotificationAndModerationAction(): void
    {
        $review = $this->createReview(Review::STATUS_PENDING, 'Avis douteux');
        $moderator = $this->createModerator();
        $author = $review->getUser();

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(fn($action) => $action instanceof ModerationAction
                && $action->getAction() === 'reject'
                && $action->getReason() === 'Contenu inapproprié'));

        $this->notificationService->expects($this->once())
            ->method('notify')
            ->with($author, 'Votre avis a été rejeté', $this->stringContains('Motif : Contenu inapproprié'));

        $this->emailService->expects($this->once())
            ->method('sendTemplate')
            ->with(
                $author->getEmail(),
                'Votre avis a été rejeté',
                'emails/review_rejected.html.twig',
                ['review' => $review, 'reason' => 'Contenu inapproprié'],
                'review_rejected',
                $author,
            );

        $this->service->reject($review, $moderator, 'Contenu inapproprié');
    }

    public function testRejectWithoutReasonDoesNotAppendMotifToMessage(): void
    {
        $review = $this->createReview(Review::STATUS_PENDING, 'Avis');
        $moderator = $this->createModerator();
        $author = $review->getUser();

        $this->notificationService->expects($this->once())
            ->method('notify')
            ->with($author, $this->anything(), $this->logicalNot($this->stringContains('Motif')));

        $this->service->reject($review, $moderator);
    }

    public function testRejectThrowsWhenReviewIsNotPending(): void
    {
        $review = $this->createReview(Review::STATUS_HIDDEN);
        $moderator = $this->createModerator();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Only a pending review can be rejected.');

        $this->service->reject($review, $moderator);
    }

    public function testHideTransitionsApprovedReviewToHidden(): void
    {
        $review = $this->createReview(Review::STATUS_APPROVED);
        $moderator = $this->createModerator();

        $this->service->hide($review, $moderator, 'Signalé par la communauté');

        $this->assertTrue($review->isHidden());
    }

    public function testHideNotifiesAndEmailsWithReason(): void
    {
        $review = $this->createReview(Review::STATUS_APPROVED, 'Avis approuvé');
        $moderator = $this->createModerator();
        $author = $review->getUser();

        $this->emailService->expects($this->once())
            ->method('sendTemplate')
            ->with(
                $author->getEmail(),
                'Votre avis a été masqué',
                'emails/review_hidden.html.twig',
                ['review' => $review, 'reason' => 'Signalé'],
                'review_hidden',
                $author,
            );

        $this->service->hide($review, $moderator, 'Signalé');
    }

    public function testHideThrowsWhenReviewIsNotApproved(): void
    {
        $review = $this->createReview(Review::STATUS_PENDING);
        $moderator = $this->createModerator();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Only an approved review can be hidden.');

        $this->service->hide($review, $moderator);
    }

    public function testHideByAdminAlsoRecordsAuditTrail(): void
    {
        $review = $this->createReview(Review::STATUS_APPROVED);
        $admin = $this->createAdmin();

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(fn($action) => $action instanceof ModerationAction
                && $action->getModerator() === $admin->getProfile()
                && $action->getAction() === 'hide'));
        $this->entityManager->expects($this->once())->method('flush');

        $this->service->hide($review, $admin);

        $this->assertTrue($review->isHidden());
    }

    public function testHideByActorWithoutAnyProfileSkipsAuditTrail(): void
    {
        $review = $this->createReview(Review::STATUS_APPROVED);
        $noProfile = new User();
        $noProfile->setEmail('ghost@example.com');

        $this->entityManager->expects($this->never())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $this->service->hide($review, $noProfile);

        $this->assertTrue($review->isHidden());
    }
}
