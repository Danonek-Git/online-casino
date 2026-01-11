<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Bet;
use App\Entity\RouletteRound;
use App\Entity\User;
use App\Repository\RouletteRoundRepository;
use Doctrine\ORM\EntityManagerInterface;

final class RouletteService
{
    private const ROUND_DURATION_SECONDS = 30;
    private const ROUND_RESULT_PAUSE_SECONDS = 15;
    private const MIN_BET_AMOUNT = 1;
    private const MAX_BET_AMOUNT = 5000;
    private const MAX_BETS_PER_ROUND = 10;
    private const DUPLICATE_BET_WINDOW_SECONDS = 3;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RouletteRoundRepository $roundRepository
    ) {
    }

    public function syncAndGetCurrentRound(): RouletteRound
    {
        $now = new \DateTimeImmutable();
        $round = $this->roundRepository->findLatest();

        if ($round === null) {
            return $this->createRound($now);
        }

        if ($round->getStatus() === RouletteRound::STATUS_OPEN && $round->getEndsAt() <= $now) {
            $this->resolveRound($round);
            return $round;
        }

        if ($round->getStatus() === RouletteRound::STATUS_FINISHED && $round->getEndsAt() <= $now) {
            $nextRoundAt = $round->getEndsAt()?->modify(sprintf('+%d seconds', self::ROUND_RESULT_PAUSE_SECONDS));
            if ($nextRoundAt !== null && $now >= $nextRoundAt) {
                return $this->createRound($now);
            }
        }

        return $round;
    }

    public function placeBet(User $user, RouletteRound $round, string $betType, string $betValue, int $amount): Bet
    {
        if ($user->isBlocked()) {
            throw new \RuntimeException('Konto jest zablokowane.');
        }

        $wallet = $user->getWallet();
        if ($wallet === null) {
            throw new \RuntimeException('Nie znaleziono portfela.');
        }

        if ($amount < self::MIN_BET_AMOUNT || $amount > self::MAX_BET_AMOUNT) {
            throw new \InvalidArgumentException('Kwota zakładu jest poza dozwolonym zakresem.');
        }

        if ($amount <= 0) {
            throw new \InvalidArgumentException('Zakład musi być większy niż 0.');
        }

        if ($wallet->getBalance() < $amount) {
            throw new \RuntimeException('Brak wystarczających środków na koncie.');
        }

        $now = new \DateTimeImmutable();
        if ($round->getStatus() !== RouletteRound::STATUS_OPEN || $round->getEndsAt() <= $now) {
            throw new \RuntimeException('Zakłady dla tej rundy zostały zamknięte.');
        }

        $betType = strtolower(trim($betType));
        $betValue = strtolower(trim($betValue));
        if (!in_array($betType, ['number', 'color', 'even'], true)) {
            throw new \InvalidArgumentException('Nieznany typ zakładu.');
        }
        if ($betType === 'number') {
            if (!ctype_digit($betValue)) {
                throw new \InvalidArgumentException('Dla typu "number" podaj liczbę 1–36.');
            }
            $number = (int) $betValue;
            if ($number < 1 || $number > 36) {
                throw new \InvalidArgumentException('Numer musi być w zakresie 1–36.');
            }
        }
        if ($betType === 'color' && !in_array($betValue, ['red', 'black'], true)) {
            throw new \InvalidArgumentException('Dla typu "color" wpisz: red lub black.');
        }
        if ($betType === 'even' && !in_array($betValue, ['even', 'odd'], true)) {
            throw new \InvalidArgumentException('Dla typu "even" wpisz: even lub odd.');
        }

        $betsInRound = 0;
        foreach ($round->getBets() as $existingBet) {
            if ($existingBet->getUser() !== $user) {
                continue;
            }

            $betsInRound++;
            if ($betsInRound >= self::MAX_BETS_PER_ROUND) {
                throw new \RuntimeException('Osiągnięto limit zakładów w tej rundzie.');
            }

            if ($existingBet->getBetType() === $betType && $existingBet->getBetValue() === $betValue) {
                $placedAt = $existingBet->getPlacedAt();
                if ($placedAt !== null && $placedAt->getTimestamp() >= $now->getTimestamp() - self::DUPLICATE_BET_WINDOW_SECONDS) {
                    throw new \RuntimeException('Zbyt szybkie powtórzenie identycznego zakładu.');
                }
            }
        }

        $bet = new Bet();
        $bet->setBetType($betType);
        $bet->setBetValue($betValue);
        $bet->setAmount($amount);
        $bet->setUser($user);
        $bet->setRound($round);
        $user->addBet($bet);
        $round->addBet($bet);

        $wallet->setBalance($wallet->getBalance() - $amount);

        $this->entityManager->persist($bet);
        $this->entityManager->flush();

        return $bet;
    }

    public function resolveRound(RouletteRound $round): void
    {
        if ($round->getStatus() !== RouletteRound::STATUS_OPEN) {
            return;
        }

        [$number, $color] = $this->drawResult();

        $round->setResultNumber($number);
        $round->setResultColor($color);
        $round->setResolvedAt(new \DateTimeImmutable());
        $round->setStatus(RouletteRound::STATUS_FINISHED);

        foreach ($round->getBets() as $b) {
            [$isWin, $payout] = $this->calculatePayout($b, $number, $color);
            $b->setIsWin($isWin);
            $b->setPayout($payout);

            if ($payout > 0) {
                $wallet = $b->getUser()->getWallet();
                if ($wallet !== null) {
                $wallet->setBalance($wallet->getBalance() + $payout);
                }
            }
        }

        $this->entityManager->flush();
    }

    /**
     * @return array<int, RouletteRound>
     */
    public function getLastResults(int $limit = 10): array
    {
        return $this->roundRepository->findLastResolved($limit);
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
            return [false, 0];
        }

        if ($type === 'number') {
            $isWin = ((string)$resultNumber === $value);
            return [$isWin, $isWin ? $amount * 36 : 0];
        }

        if ($type === 'color') {
            $value = strtolower($value);
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

    private function createRound(\DateTimeImmutable $now): RouletteRound
    {
        $round = new RouletteRound();
        $round->setStartedAt($now);
        $round->setEndsAt($now->modify(sprintf('+%d seconds', self::ROUND_DURATION_SECONDS)));
        $round->setStatus(RouletteRound::STATUS_OPEN);

        $this->entityManager->persist($round);
        $this->entityManager->flush();

        return $round;
    }
}
