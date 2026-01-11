<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\BlackjackHand;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
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

    /**
     * @return BlackjackHand[]
     */
    public function findForAdminHistory(array $filters, int $limit, int $offset, string $sort): array
    {
        $qb = $this->createQueryBuilder('hand')
            ->leftJoin('hand.user', 'user')
            ->addSelect('user');

        $this->applyAdminFilters($qb, $filters);

        return $qb
            ->orderBy('hand.createdAt', $sort)
            ->addOrderBy('hand.id', $sort)
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    public function countForAdminHistory(array $filters): int
    {
        $qb = $this->createQueryBuilder('hand')
            ->select('COUNT(hand.id)')
            ->leftJoin('hand.user', 'user');

        $this->applyAdminFilters($qb, $filters);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    private function applyAdminFilters(QueryBuilder $qb, array $filters): void
    {
        $user = trim((string) ($filters['user'] ?? ''));
        if ($user !== '') {
            $qb->andWhere('LOWER(user.email) LIKE :user')
                ->setParameter('user', '%' . mb_strtolower($user) . '%');
        }

        $result = trim((string) ($filters['result'] ?? ''));
        if ($result !== '' && in_array($result, [
            BlackjackHand::RESULT_WIN,
            BlackjackHand::RESULT_LOSE,
            BlackjackHand::RESULT_PUSH,
            BlackjackHand::RESULT_BLACKJACK,
        ], true)) {
            $qb->andWhere('hand.result = :result')->setParameter('result', $result);
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '' && in_array($status, [
            BlackjackHand::STATUS_BETTING,
            BlackjackHand::STATUS_PLAYING,
            BlackjackHand::STATUS_DEALER_TURN,
            BlackjackHand::STATUS_FINISHED,
        ], true)) {
            $qb->andWhere('hand.status = :status')->setParameter('status', $status);
        }
    }
}
