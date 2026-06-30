<?php

namespace App\Repository;

use App\Entity\Course;
use App\Entity\Review;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Review>
 */
class ReviewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Review::class);
    }

    /**
     * @return Review[]
     */
    public function findByStatus(string $status, string $direction = 'DESC'): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.status = :status')
            ->setParameter('status', $status)
            ->orderBy('r.createdAt', $direction)
            ->getQuery()
            ->getResult();
    }

    /**
     * Reviews awaiting moderation (oldest first — FIFO queue).
     *
     * @return Review[]
     */
    public function findPending(): array
    {
        return $this->findByStatus(Review::STATUS_PENDING, 'ASC');
    }

    /**
     * Publicly visible reviews (approved, newest first).
     *
     * @return Review[]
     */
    public function findPublic(): array
    {
        return $this->findByStatus(Review::STATUS_APPROVED, 'DESC');
    }

    /**
     * Reviews awaiting moderation (oldest first). Alias of {@see findPending()}.
     *
     * @return Review[]
     */
    public function findPendingReviews(): array
    {
        return $this->findByStatus(Review::STATUS_PENDING, 'ASC');
    }

    /**
     * Approved reviews for a given course (newest first).
     *
     * @return Review[]
     */
    public function findApprovedByCourse(Course $course): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.course = :course')
            ->andWhere('r.status = :status')
            ->setParameter('course', $course)
            ->setParameter('status', Review::STATUS_APPROVED)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
