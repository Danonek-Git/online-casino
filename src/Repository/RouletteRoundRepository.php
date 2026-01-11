<?php

namespace App\Repository;

use App\Entity\RouletteRound;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RouletteRound>
 */
class RouletteRoundRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RouletteRound::class);
    }

    public function findLatest(): ?RouletteRound
    {
        return $this->createQueryBuilder('round')
            ->orderBy('round.startedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findLastResolved(int $limit = 10): array
    {
        return $this->createQueryBuilder('round')
            ->andWhere('round.resultNumber IS NOT NULL')
            ->orderBy('round.resolvedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findExpiredOpenRound(\DateTimeImmutable $now): ?RouletteRound
    {
        return $this->createQueryBuilder('round')
            ->andWhere('round.status = :status')
            ->andWhere('round.endsAt <= :now')
            ->setParameter('status', RouletteRound::STATUS_OPEN)
            ->setParameter('now', $now)
            ->orderBy('round.endsAt', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countAllRounds(): int
    {
        return (int) $this->createQueryBuilder('round')
            ->select('COUNT(round.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
