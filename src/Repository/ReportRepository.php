<?php

namespace App\Repository;

use App\Entity\Report;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Report>
 */
class ReportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Report::class);
    }

    /**
     * Reports still open / unresolved (newest first).
     *
     * @return Report[]
     */
    public function findOpenReports(): array
    {
        return $this->createQueryBuilder('rp')
            ->andWhere('rp.status = :status')
            ->setParameter('status', Report::STATUS_OPEN)
            ->orderBy('rp.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
