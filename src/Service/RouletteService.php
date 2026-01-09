<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Bet;
use App\Entity\GameSession;
use App\Entity\RouletteSpin;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final class RouletteService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function playRound(User $user, string $betType, string $betValue, int $amount): array
    {
        $wallet = $user->getWallet();
        if ($wallet === null) {
            throw new \RuntimeException('User wallet not found.');
        }

        if ($amount <= 0) {
            throw new \InvalidArgumentException('Bet amount must be greater than 0.');
        }

        if ($wallet->getBalance() < $amount) {
            throw new \RuntimeException('Not enough balance.');
        }

        $session = new GameSession();
        $session->setUser($user);
        $session->setGameType(GameSession::TYPE_ROULETTE);
        $session->setStatus(GameSession::STATUS_OPEN);

        $bet = new Bet();
        $bet->setBetType($betType);
        $bet->setBetValue($betValue);
        $bet->setAmount($amount);

        $session->addBet($bet);

        $wallet->setBalance($wallet->getBalance() - $amount);

        [$number, $color] = $this->drawResult();

        $spin = new RouletteSpin();
        $spin->setResultNumber($number);
        $spin->setResultColor($color);
        $session->setRouletteSpin($spin);

        foreach ($session->getBets() as $b) {
            [$isWin, $payout] = $this->calculatePayout($b, $number, $color);
            $b->setIsWin($isWin);
            $b->setPayout($payout);

            if ($payout > 0) {
                $wallet->setBalance($wallet->getBalance() + $payout);
            }
        }

        $session->setStatus(GameSession::STATUS_FINISHED);
        $session->setFinishedAt(new \DateTimeImmutable());

        $this->entityManager->persist($session);
        $this->entityManager->persist($bet);
        $this->entityManager->persist($spin);

        $this->entityManager->flush();

        return [$spin, $bet];
    }
    /**
     * @return array{0:int,1:string} [number, color]
     */
    private function drawResult(): array
    {
        $number = random_int(0, 36);

        if ($number === 0) {
            return [0, 'green'];
        }

        $redNumbers = [
            1, 3, 5, 7, 9,
            12, 14, 16, 18,
            19, 21, 23, 25, 27,
            30, 32, 34, 36,
        ];
        $color = in_array($number, $redNumbers, true) ? 'red' : 'black';
        return [$number, $color];
    }


    /**
     * @return array{0:bool,1:int} [isWin, payout]
     */
    private function calculatePayout(Bet $bet, int $resultNumber, string $resultColor): array
    {
        $type = $bet->getBetType();
        $value = $bet->getBetValue();
        $amount = $bet->getAmount();

        if ($resultNumber === 0) {
            if ($type === 'number' && $value === '0') {
                return [true, $amount * 36];
            }

            return [false, 0];
        }

        if ($type === 'number') {
            $isWin = ((string)$resultNumber === $value);
            return [$isWin, $isWin ? $amount * 36 : 0];
        }

        if ($type === 'color') {
            $isWin = ($resultColor === $value);
            return [$isWin, $isWin ? $amount * 2 : 0];
        }

        if ($type === 'even') {
            $isEven = ($resultNumber % 2 === 0);
            $isWin = ($value === 'even' && $isEven) || ($value === 'odd' && !$isEven);

            return [$isWin, $isWin ? $amount * 2 : 0];
        }
        return [false, 0];
    }
}
