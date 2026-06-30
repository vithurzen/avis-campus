<?php

namespace App\Repository;

use App\Entity\Course;
use App\Entity\Review;
use App\Entity\ReviewRating;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ReviewRating>
 */
class ReviewRatingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReviewRating::class);
    }

    /**
     * Average score across all ratings of a course's approved reviews.
     */
    public function getAverageScoreForCourse(Course $course): ?float
    {
        $avg = $this->createQueryBuilder('rr')
            ->select('AVG(rr.score)')
            ->join('rr.review', 'r')
            ->andWhere('r.course = :course')
            ->andWhere('r.status = :status')
            ->setParameter('course', $course)
            ->setParameter('status', Review::STATUS_APPROVED)
            ->getQuery()
            ->getSingleScalarResult();

        return null === $avg ? null : (float) $avg;
    }

    /**
     * Per-criteria average scores for a course's approved reviews.
     *
     * @return array<int, array{name: string, avg: float}>
     */
    public function getAveragesByCriteriaForCourse(Course $course): array
    {
        $rows = $this->createQueryBuilder('rr')
            ->select('c.name AS name', 'AVG(rr.score) AS avg')
            ->join('rr.review', 'r')
            ->join('rr.ratingCriteria', 'c')
            ->andWhere('r.course = :course')
            ->andWhere('r.status = :status')
            ->setParameter('course', $course)
            ->setParameter('status', Review::STATUS_APPROVED)
            ->groupBy('c.id')
            ->addGroupBy('c.name')
            ->getQuery()
            ->getResult();

        return array_map(
            static fn (array $row): array => ['name' => $row['name'], 'avg' => (float) $row['avg']],
            $rows,
        );
    }
}
