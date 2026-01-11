<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\BlackjackHand;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BlackjackHand>
 */
class BlackjackHandRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BlackjackHand::class);
    }

    public function findActiveHandForUser(User $user): ?BlackjackHand
    {
        return $this->createQueryBuilder('h')
            ->andWhere('h.user = :user')
            ->andWhere('h.status != :finished')
            ->setParameter('user', $user)
            ->setParameter('finished', BlackjackHand::STATUS_FINISHED)
            ->orderBy('h.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return BlackjackHand[]
     */
    public function findRecentForUser(User $user, int $limit = 10): array
    {
        return $this->createQueryBuilder('h')
            ->andWhere('h.user = :user')
            ->andWhere('h.status = :finished')
            ->setParameter('user', $user)
            ->setParameter('finished', BlackjackHand::STATUS_FINISHED)
            ->orderBy('h.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function sumBetAmounts(): int
    {
        $result = $this->createQueryBuilder('h')
            ->select('COALESCE(SUM(h.betAmount), 0)')
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $result;
    }

    public function sumPayouts(): int
    {
        $result = $this->createQueryBuilder('h')
            ->select('COALESCE(SUM(h.payout), 0)')
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $result;
    }
}
