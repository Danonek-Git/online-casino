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
        $lossesCount = (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM bet WHERE user_id = ? AND is_win = 0',
            [$userId]
        );
        $winsCount = (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM bet WHERE user_id = ? AND is_win = 1',
            [$userId]
        );
        $lostSum = (int) $connection->fetchOne(
            'SELECT COALESCE(SUM(amount), 0) FROM bet WHERE user_id = ? AND is_win = 0',
            [$userId]
        );
        $wonSum = (int) $connection->fetchOne(
            'SELECT COALESCE(SUM(payout), 0) FROM bet WHERE user_id = ? AND is_win = 1',
            [$userId]
        );
        $biggestLoss = (int) $connection->fetchOne(
            'SELECT COALESCE(MAX(amount), 0) FROM bet WHERE user_id = ? AND is_win = 0',
            [$userId]
        );
        $biggestWin = (int) $connection->fetchOne(
            'SELECT COALESCE(MAX(payout), 0) FROM bet WHERE user_id = ? AND is_win = 1',
            [$userId]
        );
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
