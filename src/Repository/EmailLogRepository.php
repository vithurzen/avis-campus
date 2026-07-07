<?php

namespace App\Repository;

use App\Entity\EmailLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EmailLog>
 */
class EmailLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EmailLog::class);
    }

    /**
     * Whether an email of the given type was sent to $recipient at or after $since.
     * Used to throttle password-reset requests.
     */
    public function hasRecentByType(string $recipient, string $type, \DateTimeImmutable $since): bool
    {
        return (bool) $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->andWhere('e.recipient = :recipient')->setParameter('recipient', $recipient)
            ->andWhere('e.type = :type')->setParameter('type', $type)
            ->andWhere('e.sentAt >= :since')->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
