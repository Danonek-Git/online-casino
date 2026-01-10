<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\BlackjackHand;
use App\Entity\User;
use App\Service\BlackjackService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class BlackjackController extends AbstractController
{
    #[Route('/blackjack', name: 'blackjack_index', methods: ['GET'])]
    public function index(Request $request, BlackjackService $blackjackService): Response
    {
        $user = $this->getUser();
        $activeHand = null;
        $lastFinishedHand = null;
        $recentHands = [];

        if ($user instanceof User) {
            $activeHand = $blackjackService->getActiveHand($user);
            $recentHands = $blackjackService->getRecentHands($user, 5);

            // Get the most recently finished hand for the result modal
            if ($activeHand === null && !empty($recentHands)) {
                $lastFinished = $recentHands[0];
                // Only show modal if the game was finished in the last 30 seconds
                if ($lastFinished->getFinishedAt() !== null) {
                    $finishedAt = $lastFinished->getFinishedAt();
                    $now = new \DateTimeImmutable();
                    $diff = $now->getTimestamp() - $finishedAt->getTimestamp();
                    if ($diff < 30) {
                        $lastFinishedHand = $lastFinished;
                    }
                }
            }
        }

        if ($lastFinishedHand instanceof BlackjackHand) {
            $session = $request->getSession();
            $handId = $lastFinishedHand->getId();
            if ($session !== null && $handId !== null) {
                $lastModalId = $session->get('blackjack_last_modal_hand_id');
                if ($lastModalId === $handId) {
                    $lastFinishedHand = null;
                } else {
                    $session->set('blackjack_last_modal_hand_id', $handId);
                }
            }
        }

        return $this->render('blackjack/index.html.twig', [
            'activeHand' => $activeHand,
            'lastFinishedHand' => $lastFinishedHand,
            'recentHands' => $recentHands,
            'blackjackService' => $blackjackService,
        ]);
    }

    #[Route('/blackjack/deal', name: 'blackjack_deal', methods: ['POST'])]
    public function deal(Request $request, BlackjackService $blackjackService): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $amount = (int) $request->request->get('amount', 0);

        try {
            $blackjackService->startNewHand($this->getUser(), $amount);
            $this->addFlash('success', 'Rozdano karty!');
        } catch (\Throwable $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('blackjack_index');
    }

    #[Route('/blackjack/hit', name: 'blackjack_hit', methods: ['POST'])]
    public function hit(BlackjackService $blackjackService): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('blackjack_index');
        }

        $hand = $blackjackService->getActiveHand($user);
        if ($hand === null) {
            $this->addFlash('error', 'Brak aktywnej gry.');
            return $this->redirectToRoute('blackjack_index');
        }

        try {
            $blackjackService->hit($hand);
        } catch (\Throwable $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('blackjack_index');
    }

    #[Route('/blackjack/stand', name: 'blackjack_stand', methods: ['POST'])]
    public function stand(BlackjackService $blackjackService): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('blackjack_index');
        }

        $hand = $blackjackService->getActiveHand($user);
        if ($hand === null) {
            $this->addFlash('error', 'Brak aktywnej gry.');
            return $this->redirectToRoute('blackjack_index');
        }

        try {
            $blackjackService->stand($hand);
        } catch (\Throwable $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('blackjack_index');
    }

}
