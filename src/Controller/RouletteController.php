<?php

namespace App\Controller;

use App\Form\RouletteBetType;
use App\Repository\RouletteRoundRepository;
use App\Service\RouletteService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class RouletteController extends AbstractController
{
    #[Route('/roulette', name: 'roulette_index', methods: ['GET'])]
    public function index(
        Request $request,
        RouletteService $rouletteService,
        RouletteRoundRepository $roundRepository
    ): Response {
        $form = $this->createForm(RouletteBetType::class);
        $round = $rouletteService->syncAndGetCurrentRound();
        $lastRounds = $roundRepository->findLastResolved(10);

        $redNumbers = [
            1, 3, 5, 7, 9,
            12, 14, 16, 18,
            19, 21, 23, 25, 27,
            30, 32, 34, 36,
        ];

        return $this->render('roulette/index.html.twig', [
            'form' => $form->createView(),
            'round' => $round,
            'lastRounds' => $lastRounds,
            'serverTime' => new \DateTimeImmutable(),
            'wheelNumbers' => range(0, 36),
            'redNumbers' => $redNumbers,
        ]);
    }

    #[Route('/roulette/bet', name: 'roulette_bet', methods: ['POST'])]
    public function placeBet(
        Request $request,
        RouletteService $rouletteService
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $form = $this->createForm(RouletteBetType::class);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'Popraw dane zakładu.');
            return $this->redirectToRoute('roulette_index');
        }

        /** @var array{betType:string, betValue:string, amount:int} $data */
        $data = $form->getData();

        try {
            $round = $rouletteService->syncAndGetCurrentRound();
            $rouletteService->placeBet(
                $this->getUser(),
                $round,
                $data['betType'],
                $data['betValue'],
                (int) $data['amount']
            );
            $this->addFlash('success', 'Zakład przyjęty.');
        } catch (\Throwable $e) {
            $this->addFlash('error', $e->getMessage());
        }
        return $this->redirectToRoute('roulette_index');
    }

    #[Route('/roulette/state', name: 'roulette_state', methods: ['GET'])]
    public function state(
        RouletteService $rouletteService,
        RouletteRoundRepository $roundRepository
    ): JsonResponse {
        $round = $rouletteService->syncAndGetCurrentRound();
        $lastRounds = $roundRepository->findLastResolved(10);

        $history = [];
        foreach ($lastRounds as $item) {
            $history[] = [
                'number' => $item->getResultNumber(),
                'color' => $item->getResultColor(),
            ];
        }
        $balance = null;
        $user = $this->getUser();
        if ($user instanceof \App\Entity\User && $user->getWallet() !== null) {
            $balance = $user->getWallet()->getBalance();
        }

        return $this->json([
            'serverTime' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'round' => [
                'id' => $round->getId(),
                'status' => $round->getStatus(),
                'endsAt' => $round->getEndsAt()?->format(DATE_ATOM),
                'resultNumber' => $round->getResultNumber(),
                'resultColor' => $round->getResultColor(),
            ],
            'history' => $history,
            'balance' => $balance,
        ]);
    }
}
