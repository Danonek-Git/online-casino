<?php

namespace App\Controller;

use App\Entity\RouletteSpin;
use App\Form\RouletteBetType;
use App\Repository\RouletteSpinRepository;
use App\Service\RouletteService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class RouletteController extends AbstractController
{
    #[Route('/roulette', name: 'roulette_index', methods: ['GET'])]
    public function index(
        Request $request,
        RouletteSpinRepository $spinRepository
    ): Response {
        $form = $this->createForm(RouletteBetType::class);
        $form->handleRequest($request);

        $lastSpins = $spinRepository->findBy([], ['spunAt' => 'DESC'], 10);

        return $this->render('roulette/index.html.twig', [
            'form' => $form->createView(),
            'lastSpins' => $lastSpins,
        ]);
    }

    #[Route('/roulette/play', name: 'roulette_play', methods: ['POST'])]
    public function play(
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
            [$spin, $bet] = $rouletteService->playRound(
                $this->getUser(),
                $data['betType'],
                $data['betValue'],
                (int) $data['amount']
            );

            if ($bet->isWin()) {
                $this->addFlash('success', sprintf(
                    'Wygrałeś! Wynik: %d (%s). Wypłata: %d',
                    $spin->getResultNumber(),
                    $spin->getResultColor(),
                    (int) $bet->getPayout()
                ));
            } else {
                $this->addFlash('error', sprintf(
                    'Przegrałeś. Wynik: %d (%s).',
                    $spin->getResultNumber(),
                    $spin->getResultColor()
                ));
            }
        } catch (\Throwable $e) {
            $this->addFlash('error', $e->getMessage());
        }
        return $this->redirectToRoute('roulette_index');
    }
}
