<?php

namespace App\Tests\Functional;

use App\Entity\Comment;
use App\Entity\Course;
use App\Entity\Formation;
use App\Entity\ModerationAction;
use App\Entity\Report;
use App\Entity\Review;
use App\Entity\Semester;
use App\Entity\User;
use App\Enum\PeriodType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Reproduces a production bug: deleting a review/comment that still has a
 * Report or ModerationAction pointing at it violated a Postgres FK
 * constraint (those tables have no ORM-level cascade, by design, since
 * they're an audit trail meant to survive the content being deleted).
 */
final class ReviewDeletionCascadeTest extends WebTestCase
{
    private function loginAdmin(KernelBrowser $client): User
    {
        $admin = static::getContainer()->get(EntityManagerInterface::class)
            ->getRepository(User::class)
            ->createQueryBuilder('u')
            ->where('u.roles LIKE :role')
            ->setParameter('role', '%ROLE_ADMIN%')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        static::assertNotNull($admin, 'A seeded ROLE_ADMIN user must exist');
        $client->loginUser($admin);

        return $admin;
    }

    public function testDeletingAReviewWithCommentsReportsAndModerationActionsSucceeds(): void
    {
        $client = static::createClient();
        $admin = $this->loginAdmin($client);
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $formation = (new Formation())->setName('CascadeTest Formation')->setPeriodType(PeriodType::Semester);
        $em->persist($formation);
        $semester = (new Semester())->setName('S1')->setNumber(1)->setFormation($formation);
        $em->persist($semester);
        $course = (new Course())->setTitle('CascadeTest Course')->setSemester($semester)->setCreatedAt(new \DateTimeImmutable());
        $em->persist($course);

        $review = (new Review())
            ->setUser($admin)
            ->setCourse($course)
            ->setTitle('Avis à supprimer')
            ->setContent('Contenu de test')
            ->setStatus(Review::STATUS_APPROVED)
            ->setCreatedAt(new \DateTimeImmutable());
        $em->persist($review);

        $comment = (new Comment())
            ->setUser($admin)
            ->setReview($review)
            ->setContent('Commentaire de test')
            ->setStatus('visible')
            ->setCreatedAt(new \DateTimeImmutable());
        $em->persist($comment);

        // A report on the review AND one on its comment — both must survive (nulled FK),
        // not block the deletion.
        $reportOnReview = (new Report())
            ->setUser($admin)->setReview($review)->setReason('Test')->setStatus(Report::STATUS_OPEN);
        $em->persist($reportOnReview);
        $reportOnComment = (new Report())
            ->setUser($admin)->setComment($comment)->setReason('Test')->setStatus(Report::STATUS_OPEN);
        $em->persist($reportOnComment);

        $moderationAction = (new ModerationAction())
            ->setModerator($admin->getProfile())
            ->setReview($review)
            ->setAction('approve')
            ->setCreatedAt(new \DateTimeImmutable());
        $em->persist($moderationAction);

        $em->flush();
        $reviewId = $review->getId();
        $commentId = $comment->getId();
        $reportOnReviewId = $reportOnReview->getId();
        $reportOnCommentId = $reportOnComment->getId();
        $moderationActionId = $moderationAction->getId();
        $courseId = $course->getId();
        $semesterId = $semester->getId();
        $formationId = $formation->getId();
        $em->clear();

        $crawler = $client->request('GET', '/review/' . $reviewId);
        static::assertResponseIsSuccessful();
        $token = $crawler->filter('form[action="/review/' . $reviewId . '"] input[name="_token"]')->attr('value');

        $client->request('POST', '/review/' . $reviewId, ['_token' => $token]);
        static::assertResponseRedirects('/review');

        static::assertNull($em->getRepository(Review::class)->find($reviewId), 'Review is deleted');
        static::assertNull($em->getRepository(Comment::class)->find($commentId), 'Comment cascades away with the review');

        $survivingReportOnReview = $em->getRepository(Report::class)->find($reportOnReviewId);
        static::assertNotNull($survivingReportOnReview, 'Report on the review survives');
        static::assertNull($survivingReportOnReview->getReview(), 'Its review reference is nulled');

        $survivingReportOnComment = $em->getRepository(Report::class)->find($reportOnCommentId);
        static::assertNotNull($survivingReportOnComment, 'Report on the comment survives');
        static::assertNull($survivingReportOnComment->getComment(), 'Its comment reference is nulled');

        $survivingModerationAction = $em->getRepository(ModerationAction::class)->find($moderationActionId);
        static::assertNotNull($survivingModerationAction, 'ModerationAction survives (audit trail is kept)');
        static::assertNull($survivingModerationAction->getReview(), 'Its review reference is nulled');

        $em->remove($em->getRepository(Report::class)->find($reportOnReviewId));
        $em->remove($em->getRepository(Report::class)->find($reportOnCommentId));
        $em->remove($em->getRepository(ModerationAction::class)->find($moderationActionId));
        $em->remove($em->getRepository(Course::class)->find($courseId));
        $em->remove($em->getRepository(Semester::class)->find($semesterId));
        $em->remove($em->getRepository(Formation::class)->find($formationId));
        $em->flush();
    }
}
