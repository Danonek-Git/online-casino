<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Wallet;
use App\Repository\BetRepository;
use App\Repository\BlackjackHandRepository;
use App\Repository\RouletteRoundRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin', name: 'admin_')]
final class AdminController extends AbstractController
{

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/index.html.twig');
    }

    #[Route('/users', name: 'users', methods: ['GET'])]
    public function users(UserRepository $userRepository): Response
    {
        return $this->render('admin/users.html.twig', [
            'users' => $userRepository->findAll(),
        ]);
    }

    #[Route('/users/bonus', name: 'users_bonus', methods: ['POST'])]
    public function addBonus(Request $request, UserRepository $userRepository, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('admin_users_bonus', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Nieprawidłowy token.');
            return $this->redirectToRoute('admin_users');
        }

        foreach ($userRepository->findAll() as $user) {
            $wallet = $user->getWallet();
            if ($wallet === null) {
                $wallet = Wallet::createForUser($user, 0);
                $entityManager->persist($wallet);
            }
            $wallet->setBalance($wallet->getBalance() + 1000);
        }

        $entityManager->flush();
        $this->addFlash('success', 'Dodano +1000 punktów do salda wszystkim użytkownikom.');
        return $this->redirectToRoute('admin_users');
    }

    #[Route('/users/{id}/balance', name: 'user_balance', methods: ['POST'])]
    public function updateBalance(User $user, Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('admin_user_balance_' . $user->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Nieprawidłowy token.');
            return $this->redirectToRoute('admin_users');
        }

        $amount = (int) $request->request->get('balance', 0);
        $wallet = $user->getWallet();
        if ($wallet === null) {
            $wallet = Wallet::createForUser($user, 0);
            $entityManager->persist($wallet);
        }
        $wallet->setBalance($amount);
        $entityManager->flush();

        $this->addFlash('success', 'Zaktualizowano saldo.');
        return $this->redirectToRoute('admin_users');
    }

    #[Route('/users/{id}/toggle-block', name: 'user_toggle_block', methods: ['POST'])]
    public function toggleBlock(User $user, Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('admin_user_block_' . $user->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Nieprawidłowy token.');
            return $this->redirectToRoute('admin_users');
        }

        $currentUser = $this->getUser();
        if ($currentUser instanceof User && $currentUser->getId() === $user->getId()) {
            $this->addFlash('error', 'Nie możesz zablokować samego siebie.');
            return $this->redirectToRoute('admin_users');
        }

        $user->setIsBlocked(!$user->isBlocked());
        $entityManager->flush();

        $this->addFlash('success', $user->isBlocked() ? 'Użytkownik zablokowany.' : 'Użytkownik odblokowany.');
        return $this->redirectToRoute('admin_users');
    }

    #[Route('/history', name: 'history', methods: ['GET'])]
    public function history(BetRepository $betRepository, BlackjackHandRepository $blackjackHandRepository): Response
    {
        $bets = $betRepository->createQueryBuilder('bet')
            ->leftJoin('bet.user', 'user')
            ->leftJoin('bet.round', 'round')
            ->addSelect('user', 'round')
            ->orderBy('bet.placedAt', 'DESC')
            ->setMaxResults(200)
            ->getQuery()
            ->getResult();

        $blackjackHands = $blackjackHandRepository->createQueryBuilder('hand')
            ->leftJoin('hand.user', 'user')
            ->addSelect('user')
            ->orderBy('hand.createdAt', 'DESC')
            ->setMaxResults(200)
            ->getQuery()
            ->getResult();

        return $this->render('admin/history.html.twig', [
            'bets' => $bets,
            'blackjackHands' => $blackjackHands,
        ]);
    }

    #[Route('/stats', name: 'stats', methods: ['GET'])]
    public function stats(BetRepository $betRepository, RouletteRoundRepository $roundRepository, BlackjackHandRepository $blackjackHandRepository): Response
    {
        $rouletteGames = $roundRepository->countAllRounds();
        $blackjackGames = $blackjackHandRepository->count([]);
        $games = $rouletteGames + $blackjackGames;

        // Combine roulette and blackjack bets/payouts
        $totalBets = $betRepository->sumBetAmounts() + $blackjackHandRepository->sumBetAmounts();
        $totalPayouts = $betRepository->sumPayouts() + $blackjackHandRepository->sumPayouts();
        // Player losses = what they bet minus what they won back
        $losses = $totalBets - $totalPayouts;
        // Casino balance = bets received minus payouts (positive = casino profit)
        $balance = $totalBets - $totalPayouts;

        return $this->render('admin/stats.html.twig', [
            'games' => $games,
            'rouletteGames' => $rouletteGames,
            'blackjackGames' => $blackjackGames,
            'totalBets' => $totalBets,
            'totalPayouts' => $totalPayouts,
            'losses' => $losses,
            'balance' => $balance,
        ]);
    }
}
