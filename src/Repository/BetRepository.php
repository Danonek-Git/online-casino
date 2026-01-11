<?php

namespace App\Repository;

use App\Entity\Bet;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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

//    /**
//     * @return Bet[] Returns an array of Bet objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('b')
//            ->andWhere('b.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('b.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Bet
//    {
//        return $this->createQueryBuilder('b')
//            ->andWhere('b.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
