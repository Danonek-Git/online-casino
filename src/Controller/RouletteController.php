<?php

namespace App\Controller;

use App\Form\RouletteBetType;
use App\Repository\BetRepository;
use App\Repository\RouletteRoundRepository;
use App\Service\RouletteService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Psr\Cache\CacheItemPoolInterface;

final class RouletteController extends AbstractController
{
    private const CHAT_CACHE_KEY = 'roulette.chat.messages';
    private const CHAT_MAX_MESSAGES = 40;
    private const CHAT_MAX_LENGTH = 125;
    private const CHAT_TTL_SECONDS = 3600;
    private const CHAT_MAX_PER_ROUND = 5;
    private const LEADERBOARD_SIZE = 5;

    #[Route('/roulette', name: 'roulette_index', methods: ['GET'])]
    public function index(
        Request $request,
        RouletteService $rouletteService,
        RouletteRoundRepository $roundRepository,
        BetRepository $betRepository,
        CacheItemPoolInterface $cache
    ): Response {
        $form = $this->createForm(RouletteBetType::class);
        $round = $rouletteService->syncAndGetCurrentRound();
        $lastRounds = $roundRepository->findLastResolved(10);
        $lastResolvedRound = $roundRepository->findLastResolved(1)[0] ?? null;

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
            'leaderboard' => $this->buildLeaderboard($lastResolvedRound, $betRepository),
            'chatMessages' => $this->getChatMessages($cache),
            'serverTime' => new \DateTimeImmutable(),
            'wheelNumbers' => range(0, 36),
            'redNumbers' => $redNumbers,
        ]);
    }

    #[Route('/roulette/bet', name: 'roulette_bet', methods: ['POST'])]
    public function placeBet(
        Request $request,
        RouletteService $rouletteService,
        CacheItemPoolInterface $cache
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
            $user = $this->getUser();
            $wallet = $user instanceof \App\Entity\User ? $user->getWallet() : null;
            $balanceBefore = $wallet?->getBalance();
            $round = $rouletteService->syncAndGetCurrentRound();
            $rouletteService->placeBet(
                $user,
                $round,
                $data['betType'],
                $data['betValue'],
                (int) $data['amount']
            );
            if ($user instanceof \App\Entity\User) {
                $nickname = $this->extractNickname($user->getUserIdentifier());
                $betLabel = $this->formatBetLabel($data['betType'], $data['betValue']);
                $allIn = $balanceBefore !== null && (int) $data['amount'] >= $balanceBefore;
                $message = $allIn
                    ? sprintf('postawił all in na %s', $betLabel)
                    : sprintf('postawił %s za %d zł', $betLabel, (int) $data['amount']);
                $this->addChatMessage($cache, $nickname, $message);
            }
            $this->addFlash('success', 'Zakład przyjęty.');
        } catch (\Throwable $e) {
            $this->addFlash('error', $e->getMessage());
        }
        return $this->redirectToRoute('roulette_index');
    }

    #[Route('/roulette/state', name: 'roulette_state', methods: ['GET'])]
    public function state(
        RouletteService $rouletteService,
        RouletteRoundRepository $roundRepository,
        BetRepository $betRepository,
        CacheItemPoolInterface $cache
    ): JsonResponse {
        $round = $rouletteService->syncAndGetCurrentRound();
        $lastRounds = $roundRepository->findLastResolved(10);
        $lastResolvedRound = $roundRepository->findLastResolved(1)[0] ?? null;

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
            'leaderboard' => $this->buildLeaderboard($lastResolvedRound, $betRepository),
            'chat' => $this->getChatMessages($cache),
        ]);
    }

    #[Route('/roulette/chat/send', name: 'roulette_chat_send', methods: ['POST'])]
    public function sendChatMessage(
        Request $request,
        CacheItemPoolInterface $cache,
        RouletteService $rouletteService
    ): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $text = trim((string) $request->request->get('message', ''));
        $length = function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
        if ($text === '' || $length > self::CHAT_MAX_LENGTH) {
            return $this->chatErrorResponse($request, 'Niepoprawna wiadomość.', Response::HTTP_BAD_REQUEST);
        }

        $user = $this->getUser();
        $author = $user instanceof \App\Entity\User ? $this->extractNickname($user->getUserIdentifier()) : 'Anonim';
        $roundId = $rouletteService->syncAndGetCurrentRound()->getId();
        if ($roundId !== null && $user instanceof \App\Entity\User) {
            $countKey = sprintf('roulette.chat.count.%d.%d', $roundId, $user->getId());
            $countItem = $cache->getItem($countKey);
            $count = $countItem->isHit() ? (int) $countItem->get() : 0;
            if ($count >= self::CHAT_MAX_PER_ROUND) {
                return $this->chatErrorResponse(
                    $request,
                    'Limit wiadomości na tę rundę został wyczerpany.',
                    Response::HTTP_BAD_REQUEST
                );
            }
            $countItem->set($count + 1);
            $countItem->expiresAfter(self::CHAT_TTL_SECONDS);
            $cache->save($countItem);
        }

        $messages = $this->addChatMessage($cache, $author, $text);

        if ($this->wantsJson($request)) {
            return $this->json(['ok' => true, 'messages' => $messages]);
        }

        return $this->redirectToRoute('roulette_index');
    }

    /**
     * @return array<int, array{user:string,text:string,time:string}>
     */
    private function getChatMessages(CacheItemPoolInterface $cache): array
    {
        $item = $cache->getItem(self::CHAT_CACHE_KEY);
        if (!$item->isHit()) {
            return [];
        }

        $messages = $item->get();
        if (!is_array($messages)) {
            return [];
        }

        return array_slice($messages, -self::CHAT_MAX_MESSAGES);
    }

    /**
     * @return array<int, array{user:string,text:string,time:string}>
     */
    private function addChatMessage(CacheItemPoolInterface $cache, string $user, string $text): array
    {
        $messages = $this->getChatMessages($cache);
        $messages[] = [
            'user' => $user,
            'text' => $text,
            'time' => (new \DateTimeImmutable())->format('H:i'),
        ];
        $messages = array_slice($messages, -self::CHAT_MAX_MESSAGES);

        $item = $cache->getItem(self::CHAT_CACHE_KEY);
        $item->set($messages);
        $item->expiresAfter(self::CHAT_TTL_SECONDS);
        $cache->save($item);

        return $messages;
    }

    private function extractNickname(string $identifier): string
    {
        $parts = explode('@', $identifier, 2);
        return $parts[0] !== '' ? $parts[0] : $identifier;
    }

    private function formatBetLabel(string $betType, string $betValue): string
    {
        $type = strtolower(trim($betType));
        $value = strtolower(trim($betValue));

        if ($type === 'color') {
            return $value === 'red' ? 'czerwone' : ($value === 'black' ? 'czarne' : $betValue);
        }
        if ($type === 'even') {
            return $value === 'even' ? 'parzyste' : ($value === 'odd' ? 'nieparzyste' : $betValue);
        }
        if ($type === 'number') {
            return sprintf('numer %s', $betValue);
        }
        return $betValue;
    }

    private function chatErrorResponse(Request $request, string $message, int $status): Response
    {
        if ($this->wantsJson($request)) {
            return $this->json(['ok' => false, 'error' => $message], $status);
        }

        $this->addFlash('error', $message);
        return $this->redirectToRoute('roulette_index');
    }

    private function wantsJson(Request $request): bool
    {
        if ($request->isXmlHttpRequest()) {
            return true;
        }

        $accept = (string) $request->headers->get('Accept', '');
        return str_contains($accept, 'application/json');
    }

    /**
     * @return array{roundId:int|null,winners:array<int,array{user:string,amount:int}>,losers:array<int,array{user:string,amount:int}>}
     */
    private function buildLeaderboard(?\App\Entity\RouletteRound $round, BetRepository $betRepository): array
    {
        if ($round === null) {
            return ['roundId' => null, 'winners' => [], 'losers' => []];
        }
        $roundId = $round->getId();
        if ($roundId === null) {
            return ['roundId' => null, 'winners' => [], 'losers' => []];
        }

        $winners = $betRepository->getTopWinnersByRound($roundId, self::LEADERBOARD_SIZE);
        $losers = $betRepository->getTopLosersByRound($roundId, self::LEADERBOARD_SIZE);
        return [
            'roundId' => $roundId,
            'winners' => $winners,
            'losers' => $losers,
        ];
    }
}
