<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\BlackjackHand;
use App\Entity\GameSession;
use App\Entity\User;
use App\Repository\BlackjackHandRepository;
use Doctrine\ORM\EntityManagerInterface;

final class BlackjackService
{
    private const SUITS = ['H', 'D', 'C', 'S'];
    private const RANKS = ['2', '3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K', 'A'];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly BlackjackHandRepository $handRepository
    ) {
    }

    public function getActiveHand(User $user): ?BlackjackHand
    {
        return $this->handRepository->findActiveHandForUser($user);
    }

    /**
     * @return BlackjackHand[]
     */
    public function getRecentHands(User $user, int $limit = 10): array
    {
        return $this->handRepository->findRecentForUser($user, $limit);
    }

    public function startNewHand(User $user, int $betAmount): BlackjackHand
    {
        $existingHand = $this->getActiveHand($user);
        if ($existingHand !== null) {
            throw new \RuntimeException('Masz już rozdane karty.');
        }

        $wallet = $user->getWallet();
        if ($wallet === null) {
            throw new \RuntimeException('Nie znaleziono portfela użytkownika.');
        }

        if ($betAmount <= 0) {
            throw new \InvalidArgumentException('Zakład musi być większy niż 0.');
        }

        if ($wallet->getBalance() < $betAmount) {
            throw new \RuntimeException('Brak wystarczających środków.');
        }

        $wallet->setBalance($wallet->getBalance() - $betAmount);

        $gameSession = new GameSession();
        $gameSession->setUser($user);
        $gameSession->setGameType(GameSession::TYPE_BLACKJACK);
        $this->entityManager->persist($gameSession);

        $hand = new BlackjackHand();
        $hand->setUser($user);
        $hand->setBetAmount($betAmount);
        $hand->setGameSession($gameSession);
        $hand->setStatus(BlackjackHand::STATUS_PLAYING);

        $deck = $this->createShuffledDeck();

        $hand->addPlayerCard(array_pop($deck));
        $hand->addDealerCard(array_pop($deck));
        $hand->addPlayerCard(array_pop($deck));
        $hand->addDealerCard(array_pop($deck));

        $this->entityManager->persist($hand);
        $this->entityManager->flush();

        return $hand;
    }

    public function hit(BlackjackHand $hand): BlackjackHand
    {
        if ($hand->getStatus() !== BlackjackHand::STATUS_PLAYING) {
            throw new \RuntimeException('Nie można dobrać, gra nie jest aktywna.');
        }

        $deck = $this->createShuffledDeck();
        $usedCards = array_merge($hand->getPlayerCards(), $hand->getDealerCards());
        $deck = array_values(array_diff($deck, $usedCards));

        if (empty($deck)) {
            $deck = $this->createShuffledDeck();
        }

        $hand->addPlayerCard($deck[array_rand($deck)]);

        $playerValue = $this->calculateHandValue($hand->getPlayerCards());

        if ($playerValue > 21) {
            $hand->setStatus(BlackjackHand::STATUS_FINISHED);
            $hand->setResult(BlackjackHand::RESULT_LOSE);
            $hand->setPayout(0);
            $hand->setFinishedAt(new \DateTimeImmutable());

            $gameSession = $hand->getGameSession();
            if ($gameSession !== null) {
                $gameSession->setStatus(GameSession::STATUS_FINISHED);
                $gameSession->setFinishedAt(new \DateTimeImmutable());
            }
        } elseif ($playerValue === 21) {
            $this->stand($hand);
            return $hand;
        }

        $this->entityManager->flush();

        return $hand;
    }

    public function stand(BlackjackHand $hand): BlackjackHand
    {
        if ($hand->getStatus() !== BlackjackHand::STATUS_PLAYING) {
            throw new \RuntimeException('Nie można dobrać, gra nie jest aktywna.');
        }

        $hand->setStatus(BlackjackHand::STATUS_DEALER_TURN);

        $deck = $this->createShuffledDeck();
        $usedCards = array_merge($hand->getPlayerCards(), $hand->getDealerCards());
        $deck = array_values(array_diff($deck, $usedCards));

        if (empty($deck)) {
            $deck = $this->createShuffledDeck();
        }

        $this->finishHand($hand, $deck);
        $this->entityManager->flush();

        return $hand;
    }

    private function finishHand(BlackjackHand $hand, array $deck): void
    {
        $playerValue = $this->calculateHandValue($hand->getPlayerCards());
        $playerBlackjack = count($hand->getPlayerCards()) === 2 && $playerValue === 21;

        while ($this->calculateHandValue($hand->getDealerCards()) < 17) {
            $availableCards = array_values(array_diff($deck, $hand->getDealerCards()));
            if (empty($availableCards)) {
                $availableCards = $this->createShuffledDeck();
            }
            $hand->addDealerCard($availableCards[array_rand($availableCards)]);
        }

        $dealerValue = $this->calculateHandValue($hand->getDealerCards());
        $dealerBlackjack = count($hand->getDealerCards()) === 2 && $dealerValue === 21;

        $betAmount = $hand->getBetAmount();
        $payout = 0;
        $result = BlackjackHand::RESULT_LOSE;

        if ($playerBlackjack && !$dealerBlackjack) {
            $result = BlackjackHand::RESULT_BLACKJACK;
            $payout = (int) ($betAmount * 2.5);
        } elseif ($playerBlackjack && $dealerBlackjack) {
            $result = BlackjackHand::RESULT_PUSH;
            $payout = $betAmount;
        } elseif ($dealerValue > 21) {
            $result = BlackjackHand::RESULT_WIN;
            $payout = $betAmount * 2;
        } elseif ($playerValue > $dealerValue) {
            $result = BlackjackHand::RESULT_WIN;
            $payout = $betAmount * 2;
        } elseif ($playerValue === $dealerValue) {
            $result = BlackjackHand::RESULT_PUSH;
            $payout = $betAmount;
        }

        $hand->setStatus(BlackjackHand::STATUS_FINISHED);
        $hand->setResult($result);
        $hand->setPayout($payout);
        $hand->setFinishedAt(new \DateTimeImmutable());

        if ($payout > 0) {
            $wallet = $hand->getUser()->getWallet();
            if ($wallet !== null) {
                $wallet->setBalance($wallet->getBalance() + $payout);
            }
        }

        $gameSession = $hand->getGameSession();
        if ($gameSession !== null) {
            $gameSession->setStatus(GameSession::STATUS_FINISHED);
            $gameSession->setFinishedAt(new \DateTimeImmutable());
        }
    }

    public function calculateHandValue(array $cards): int
    {
        $value = 0;
        $aces = 0;

        foreach ($cards as $card) {
            $rank = $this->getCardRank($card);

            if ($rank === 'A') {
                $aces++;
                $value += 11;
            } elseif (in_array($rank, ['K', 'Q', 'J'], true)) {
                $value += 10;
            } else {
                $value += (int) $rank;
            }
        }

        while ($value > 21 && $aces > 0) {
            $value -= 10;
            $aces--;
        }

        return $value;
    }

    public function getCardRank(string $card): string
    {
        return substr($card, 0, -1);
    }

    public function getCardSuit(string $card): string
    {
        return substr($card, -1);
    }

    public function getCardDisplay(string $card): array
    {
        $rank = $this->getCardRank($card);
        $suit = $this->getCardSuit($card);

        $suitSymbols = [
            'H' => '♥',
            'D' => '♦',
            'C' => '♣',
            'S' => '♠',
        ];

        $suitColors = [
            'H' => 'red',
            'D' => 'red',
            'C' => 'black',
            'S' => 'black',
        ];

        return [
            'rank' => $rank,
            'suit' => $suitSymbols[$suit] ?? $suit,
            'color' => $suitColors[$suit] ?? 'black',
        ];
    }

    private function createShuffledDeck(): array
    {
        $deck = [];
        foreach (self::SUITS as $suit) {
            foreach (self::RANKS as $rank) {
                $deck[] = $rank . $suit;
            }
        }
        shuffle($deck);

        return $deck;
    }
}
