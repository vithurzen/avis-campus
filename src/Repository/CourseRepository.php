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
     * @return Course[]
     */
    public function findTopCourses(int $limit = 10): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = '
            SELECT c.id, COALESCE(AVG(rr.score), 0) AS avg_score
            FROM courses c
            LEFT JOIN reviews r ON r.course_id = c.id AND r.status = :status
            LEFT JOIN review_ratings rr ON rr.review_id = r.id
            GROUP BY c.id
            ORDER BY avg_score DESC
            LIMIT :lim
        ';
        $rows = $conn->executeQuery($sql, ['status' => 'published', 'lim' => $limit])->fetchAllAssociative();
        $ids = array_column($rows, 'id');

        if (empty($ids)) {
            return [];
        }

        // Charger les entités avec leurs relations en une seule requête
        $courses = $this->createQueryBuilder('c')
            ->where('c.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->leftJoin('c.semester', 's')->addSelect('s')
            ->leftJoin('s.formation', 'f')->addSelect('f')
            ->leftJoin('c.tags', 't')->addSelect('t')
            ->getQuery()
            ->getResult();

        // Réordonner selon le classement par note
        $indexed = [];
        foreach ($courses as $course) {
            $indexed[$course->getId()] = $course;
        }

        $ordered = [];
        foreach ($ids as $id) {
            if (isset($indexed[$id])) {
                $ordered[] = $indexed[$id];
            }
        }

        return $ordered;
    }

    /**
     * @return Course[]
     */
    public function findAllWithRelations(): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.semester', 's')->addSelect('s')
            ->leftJoin('s.formation', 'f')->addSelect('f')
            ->leftJoin('c.tags', 't')->addSelect('t')
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
