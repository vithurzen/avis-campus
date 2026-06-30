<?php

namespace App\Repository;

use App\Entity\Course;
use App\Entity\Formation;
use App\Entity\RatingCriteria;
use App\Entity\Review;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Course>
 */
class CourseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Course::class);
    }

    /**
     * Courses ranked by their average approved-review score (highest first).
     *
     * @return array<int, array{course: Course, avgScore: float}>
     */
    public function findTopRatedCourses(int $limit = 10): array
    {
        return $this->createQueryBuilder('c')
            ->select('c AS course', 'AVG(rr.score) AS avgScore')
            ->join('c.reviews', 'r')
            ->join('r.ratings', 'rr')
            ->andWhere('r.status = :status')
            ->setParameter('status', Review::STATUS_APPROVED)
            ->groupBy('c.id')
            ->orderBy('avgScore', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Courses ranked by their average "Difficulté" score (most difficult first).
     *
     * @return array<int, array{course: Course, avgScore: float}>
     */
    public function findMostDifficultCourses(int $limit = 10): array
    {
        return $this->createQueryBuilder('c')
            ->select('c AS course', 'AVG(rr.score) AS avgScore')
            ->join('c.reviews', 'r')
            ->join('r.ratings', 'rr')
            ->join('rr.ratingCriteria', 'rc')
            ->andWhere('r.status = :status')
            ->andWhere('rc.name = :criteria')
            ->setParameter('status', Review::STATUS_APPROVED)
            ->setParameter('criteria', RatingCriteria::CRITERIA_DIFFICULTY)
            ->groupBy('c.id')
            ->orderBy('avgScore', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Courses belonging to a given formation and semester number.
     *
     * @return Course[]
     */
    public function findByFormationAndSemester(Formation $formation, int $semesterNumber): array
    {
        return $this->createQueryBuilder('c')
            ->join('c.semester', 's')
            ->andWhere('s.formation = :formation')
            ->andWhere('s.number = :number')
            ->setParameter('formation', $formation)
            ->setParameter('number', $semesterNumber)
            ->orderBy('c.title', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
