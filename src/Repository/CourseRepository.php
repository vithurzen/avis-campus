<?php

namespace App\Repository;

use App\Entity\Course;
use App\Entity\Formation;
use App\Entity\RatingCriteria;
use App\Entity\Review;
use App\Entity\Semester;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
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
     * Cours non encore reviewés par l'utilisateur (pour le formulaire de dépôt d'avis).
     * Si $excludedCourse est fourni (cas édition), ce cours est réintégré dans les résultats.
     * Si $semester est fourni, seuls les cours de ce semestre sont retournés (cascade du formulaire).
     */
    public function findCoursesNotReviewedBy(User $user, ?Course $excludedCourse = null, ?Semester $semester = null): QueryBuilder
    {
        $qb = $this->createQueryBuilder('c')->orderBy('c.title', 'ASC');

        // Sous-requête DQL : IDs des cours déjà reviewés par l'utilisateur
        $dql = 'SELECT IDENTITY(r.course) FROM App\Entity\Review r WHERE r.user = :user';
        $qb->setParameter('user', $user);

        // En mode édition, on exclut le cours courant de la sous-requête pour qu'il reste disponible
        if ($excludedCourse !== null) {
            $dql .= ' AND r.course != :excludedCourse';
            $qb->setParameter('excludedCourse', $excludedCourse);
        }

        $qb->where("c.id NOT IN ($dql)");

        // Cascade Formation → Semestre → Cours : on limite au semestre choisi
        if ($semester !== null) {
            $qb->andWhere('c.semester = :semester')->setParameter('semester', $semester);
        }

        return $qb;
    }

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
            ->leftJoin('c.reviews', 'r')->addSelect('r')
            ->orderBy('c.title', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
