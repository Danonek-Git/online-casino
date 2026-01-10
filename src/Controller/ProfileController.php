<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class ProfileController extends AbstractController
{
    #[Route('/profile', name: 'app_profile', methods: ['GET'])]
    public function index(Connection $connection): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $userId = $user->getId();

        // Losses: roulette losses + blackjack losses
        $rouletteLosses = (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM bet WHERE user_id = ? AND is_win = 0',
            [$userId]
        );
        $blackjackLosses = (int) $connection->fetchOne(
            "SELECT COUNT(*) FROM blackjack_hand WHERE user_id = ? AND status = 'finished' AND result = 'lose'",
            [$userId]
        );
        $lossesCount = $rouletteLosses + $blackjackLosses;

        // Wins: roulette wins + blackjack wins/blackjacks
        $rouletteWins = (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM bet WHERE user_id = ? AND is_win = 1',
            [$userId]
        );
        $blackjackWins = (int) $connection->fetchOne(
            "SELECT COUNT(*) FROM blackjack_hand WHERE user_id = ? AND status = 'finished' AND result IN ('win', 'blackjack')",
            [$userId]
        );
        $winsCount = $rouletteWins + $blackjackWins;

        // Lost sum: roulette + blackjack
        $rouletteLostSum = (int) $connection->fetchOne(
            'SELECT COALESCE(SUM(amount), 0) FROM bet WHERE user_id = ? AND is_win = 0',
            [$userId]
        );
        $blackjackLostSum = (int) $connection->fetchOne(
            "SELECT COALESCE(SUM(bet_amount), 0) FROM blackjack_hand WHERE user_id = ? AND status = 'finished' AND result = 'lose'",
            [$userId]
        );
        $lostSum = $rouletteLostSum + $blackjackLostSum;

        // Won sum: roulette + blackjack (payout - bet for net win)
        $rouletteWonSum = (int) $connection->fetchOne(
            'SELECT COALESCE(SUM(payout), 0) FROM bet WHERE user_id = ? AND is_win = 1',
            [$userId]
        );
        $blackjackWonSum = (int) $connection->fetchOne(
            "SELECT COALESCE(SUM(payout - bet_amount), 0) FROM blackjack_hand WHERE user_id = ? AND status = 'finished' AND result IN ('win', 'blackjack')",
            [$userId]
        );
        $wonSum = $rouletteWonSum + $blackjackWonSum;

        // Biggest loss
        $rouletteBiggestLoss = (int) $connection->fetchOne(
            'SELECT COALESCE(MAX(amount), 0) FROM bet WHERE user_id = ? AND is_win = 0',
            [$userId]
        );
        $blackjackBiggestLoss = (int) $connection->fetchOne(
            "SELECT COALESCE(MAX(bet_amount), 0) FROM blackjack_hand WHERE user_id = ? AND status = 'finished' AND result = 'lose'",
            [$userId]
        );
        $biggestLoss = max($rouletteBiggestLoss, $blackjackBiggestLoss);

        // Biggest win
        $rouletteBiggestWin = (int) $connection->fetchOne(
            'SELECT COALESCE(MAX(payout), 0) FROM bet WHERE user_id = ? AND is_win = 1',
            [$userId]
        );
        $blackjackBiggestWin = (int) $connection->fetchOne(
            "SELECT COALESCE(MAX(payout - bet_amount), 0) FROM blackjack_hand WHERE user_id = ? AND status = 'finished' AND result IN ('win', 'blackjack')",
            [$userId]
        );
        $biggestWin = max($rouletteBiggestWin, $blackjackBiggestWin);
        $totalBets = $lossesCount + $winsCount;
        $luck = $totalBets > 0 ? round(($winsCount / $totalBets) * 100) : 0;

        return $this->render('profile/index.html.twig', [
            'lossesCount' => $lossesCount,
            'winsCount' => $winsCount,
            'lostSum' => $lostSum,
            'wonSum' => $wonSum,
            'biggestLoss' => $biggestLoss,
            'biggestWin' => $biggestWin,
            'luck' => $luck,
        ]);
    }
}
