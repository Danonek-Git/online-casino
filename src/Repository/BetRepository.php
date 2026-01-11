<?php

namespace App\Repository;

use App\Entity\Bet;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Bet>
 */
class BetRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Bet::class);
    }

    public function sumBetAmounts(): int
    {
        $result = $this->createQueryBuilder('bet')
            ->select('COALESCE(SUM(bet.amount), 0)')
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $result;
    }

    public function sumPayouts(): int
    {
        $result = $this->createQueryBuilder('bet')
            ->select('COALESCE(SUM(bet.payout), 0)')
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $result;
    }

    /**
     * @return Bet[]
     */
    public function findForAdminHistory(array $filters, int $limit, int $offset, string $sort): array
    {
        $qb = $this->createQueryBuilder('bet')
            ->leftJoin('bet.user', 'user')
            ->leftJoin('bet.round', 'round')
            ->addSelect('user', 'round');

        $this->applyAdminFilters($qb, $filters);

        return $qb
            ->orderBy('bet.placedAt', $sort)
            ->addOrderBy('bet.id', $sort)
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    public function countForAdminHistory(array $filters): int
    {
        $qb = $this->createQueryBuilder('bet')
            ->select('COUNT(bet.id)')
            ->leftJoin('bet.user', 'user')
            ->leftJoin('bet.round', 'round');

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

        $betNumber = trim((string) ($filters['betNumber'] ?? ''));
        $betColor = strtolower(trim((string) ($filters['betColor'] ?? '')));
        if ($betNumber !== '' && ctype_digit($betNumber)) {
            $qb->andWhere('bet.betType = :betTypeNumber')
                ->andWhere('bet.betValue = :betNumber')
                ->setParameter('betTypeNumber', 'number')
                ->setParameter('betNumber', $betNumber);
        } elseif ($betColor !== '' && in_array($betColor, ['red', 'black'], true)) {
            $qb->andWhere('bet.betType = :betTypeColor')
                ->andWhere('bet.betValue = :betColor')
                ->setParameter('betTypeColor', 'color')
                ->setParameter('betColor', $betColor);
        }

        $isWin = (string) ($filters['isWin'] ?? '');
        if ($isWin === 'yes') {
            $qb->andWhere('bet.isWin = :isWin')->setParameter('isWin', true);
        } elseif ($isWin === 'no') {
            $qb->andWhere('bet.isWin = :isWin')->setParameter('isWin', false);
        }

        $roundId = trim((string) ($filters['roundId'] ?? ''));
        if ($roundId !== '' && ctype_digit($roundId)) {
            $qb->andWhere('round.id = :roundId')->setParameter('roundId', (int) $roundId);
        }
    }

    /**
     * @return array<int, array{user:string,amount:int}>
     */
    public function getTopWinnersByRound(int $roundId, int $limit): array
    {
        $rows = $this->createQueryBuilder('bet')
            ->select('betUser.email AS user', 'COALESCE(SUM(bet.payout), 0) AS amount')
            ->leftJoin('bet.user', 'betUser')
            ->leftJoin('bet.round', 'round')
            ->andWhere('round.id = :roundId')
            ->andWhere('bet.isWin = :isWin')
            ->setParameter('roundId', $roundId)
            ->setParameter('isWin', true)
            ->groupBy('betUser.id')
            ->orderBy('amount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        return array_map(
            static fn (array $row) => ['user' => (string) $row['user'], 'amount' => (int) $row['amount']],
            $rows
        );
    }

    /**
     * @return array<int, array{user:string,amount:int}>
     */
    public function getTopLosersByRound(int $roundId, int $limit): array
    {
        $rows = $this->createQueryBuilder('bet')
            ->select('betUser.email AS user', 'COALESCE(SUM(bet.amount), 0) AS amount')
            ->leftJoin('bet.user', 'betUser')
            ->leftJoin('bet.round', 'round')
            ->andWhere('round.id = :roundId')
            ->andWhere('bet.isWin = :isWin')
            ->setParameter('roundId', $roundId)
            ->setParameter('isWin', false)
            ->groupBy('betUser.id')
            ->orderBy('amount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        return array_map(
            static fn (array $row) => ['user' => (string) $row['user'], 'amount' => (int) $row['amount']],
            $rows
        );
    }
}
