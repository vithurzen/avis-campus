<?php

namespace App\Repository;

use App\Entity\Course;
use App\Entity\Favorite;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Favorite>
 */
class FavoriteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Favorite::class);
    }

    /**
     * The favorite linking a user to a course, if any (the pair is unique).
     */
    public function findOneByUserAndCourse(User $user, Course $course): ?Favorite
    {
        return $this->findOneBy(['user' => $user, 'course' => $course]);
    }

    /**
     * A user's favorites, most recent first.
     *
     * @return Favorite[]
     */
    public function findByUser(User $user): array
    {
        return $this->findBy(['user' => $user], ['createdAt' => 'DESC']);
    }
}
