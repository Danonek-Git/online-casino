<?php

namespace App\Controller;

use App\Entity\Article;
use App\Entity\User;
use App\Entity\Wallet;
use App\Repository\ArticleRepository;
use App\Repository\BetRepository;
use App\Repository\GameSessionRepository;
use App\Repository\UserRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin', name: 'admin_')]
final class AdminController extends AbstractController
{
    private const ARTICLE_IMAGES = [
        '/assets/articles/roulette.webp',
        '/assets/articles/blackjack.webp',
        '/assets/articles/slots.webp',
    ];

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
        $this->addFlash('success', 'Dodano +1000 wszystkim użytkownikom.');
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
    public function history(BetRepository $betRepository, GameSessionRepository $gameSessionRepository): Response
    {
        $bets = $betRepository->createQueryBuilder('bet')
            ->leftJoin('bet.user', 'user')
            ->leftJoin('bet.round', 'round')
            ->addSelect('user', 'round')
            ->orderBy('bet.placedAt', 'DESC')
            ->setMaxResults(200)
            ->getQuery()
            ->getResult();

        $sessions = $gameSessionRepository->createQueryBuilder('session')
            ->leftJoin('session.user', 'user')
            ->addSelect('user')
            ->orderBy('session.startedAt', 'DESC')
            ->setMaxResults(200)
            ->getQuery()
            ->getResult();

        return $this->render('admin/history.html.twig', [
            'bets' => $bets,
            'sessions' => $sessions,
        ]);
    }

    #[Route('/stats', name: 'stats', methods: ['GET'])]
    public function stats(Connection $connection): Response
    {
        $games = (int) $connection->fetchOne('SELECT COUNT(*) FROM roulette_round');
        $totalBets = (int) $connection->fetchOne('SELECT COALESCE(SUM(amount), 0) FROM bet');
        $totalPayouts = (int) $connection->fetchOne('SELECT COALESCE(SUM(payout), 0) FROM bet');
        $losses = $totalBets - $totalPayouts;
        $balance = $totalPayouts - $totalBets;

        return $this->render('admin/stats.html.twig', [
            'games' => $games,
            'totalBets' => $totalBets,
            'totalPayouts' => $totalPayouts,
            'losses' => $losses,
            'balance' => $balance,
        ]);
    }

    #[Route('/articles', name: 'articles', methods: ['GET'])]
    public function articles(ArticleRepository $articleRepository): Response
    {
        return $this->render('admin/articles/index.html.twig', [
            'articles' => $articleRepository->findBy([], ['createdAt' => 'DESC']),
            'images' => self::ARTICLE_IMAGES,
        ]);
    }

    #[Route('/articles/new', name: 'articles_new', methods: ['GET', 'POST'])]
    public function articlesNew(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('admin_article_new', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Nieprawidłowy token.');
                return $this->redirectToRoute('admin_articles_new');
            }

            $title = trim((string) $request->request->get('title'));
            $image = (string) $request->request->get('imagePath');
            $content = trim((string) $request->request->get('content'));

            if ($title === '' || $image === '') {
                $this->addFlash('error', 'Uzupełnij tytuł i obrazek.');
                return $this->redirectToRoute('admin_articles_new');
            }

            $article = new Article();
            $article->setTitle($title);
            $article->setImagePath($image);
            $article->setContent($content !== '' ? $content : null);
            $entityManager->persist($article);
            $entityManager->flush();

            $this->addFlash('success', 'Artykuł dodany.');
            return $this->redirectToRoute('admin_articles');
        }

        return $this->render('admin/articles/form.html.twig', [
            'article' => null,
            'images' => self::ARTICLE_IMAGES,
            'csrf_token' => $this->get('security.csrf.token_manager')->getToken('admin_article_new')->getValue(),
        ]);
    }

    #[Route('/articles/{id}/edit', name: 'articles_edit', methods: ['GET', 'POST'])]
    public function articlesEdit(Article $article, Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('admin_article_edit_' . $article->getId(), (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Nieprawidłowy token.');
                return $this->redirectToRoute('admin_articles_edit', ['id' => $article->getId()]);
            }

            $title = trim((string) $request->request->get('title'));
            $image = (string) $request->request->get('imagePath');
            $content = trim((string) $request->request->get('content'));

            if ($title === '' || $image === '') {
                $this->addFlash('error', 'Uzupełnij tytuł i obrazek.');
                return $this->redirectToRoute('admin_articles_edit', ['id' => $article->getId()]);
            }

            $article->setTitle($title);
            $article->setImagePath($image);
            $article->setContent($content !== '' ? $content : null);
            $entityManager->flush();

            $this->addFlash('success', 'Artykuł zaktualizowany.');
            return $this->redirectToRoute('admin_articles');
        }

        return $this->render('admin/articles/form.html.twig', [
            'article' => $article,
            'images' => self::ARTICLE_IMAGES,
            'csrf_token' => $this->get('security.csrf.token_manager')->getToken('admin_article_edit_' . $article->getId())->getValue(),
        ]);
    }

    #[Route('/articles/{id}/delete', name: 'articles_delete', methods: ['POST'])]
    public function articlesDelete(Article $article, Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('admin_article_delete_' . $article->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Nieprawidłowy token.');
            return $this->redirectToRoute('admin_articles');
        }

        $entityManager->remove($article);
        $entityManager->flush();

        $this->addFlash('success', 'Artykuł usunięty.');
        return $this->redirectToRoute('admin_articles');
    }
}
