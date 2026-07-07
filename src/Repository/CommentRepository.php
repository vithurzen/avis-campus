<?php

namespace App\Repository;

use App\Entity\Comment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Comment>
 */
class CommentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Comment::class);
    }

    /**
     * Reports and moderation actions may reference this comment via nullable FKs with no
     * ORM-level cascade (they're an audit trail, kept even once the content is gone).
     * Must be detached before the comment is removed, or Postgres rejects the delete
     * with a FK violation.
     */
    public function detachAuditReferencesBeforeDelete(Comment $comment): void
    {
        $em = $this->getEntityManager();

        $em->createQuery('UPDATE App\Entity\Report r SET r.comment = NULL WHERE r.comment = :comment')
            ->setParameter('comment', $comment)->execute();
        $em->createQuery('UPDATE App\Entity\ModerationAction m SET m.comment = NULL WHERE m.comment = :comment')
            ->setParameter('comment', $comment)->execute();
    }
}
