<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\BlackjackHandRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BlackjackHandRepository::class)]
class BlackjackHand
{
    public const STATUS_BETTING = 'betting';
    public const STATUS_PLAYING = 'playing';
    public const STATUS_DEALER_TURN = 'dealer_turn';
    public const STATUS_FINISHED = 'finished';

    public const RESULT_WIN = 'win';
    public const RESULT_LOSE = 'lose';
    public const RESULT_PUSH = 'push';
    public const RESULT_BLACKJACK = 'blackjack';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column]
    private array $playerCards = [];

    #[ORM\Column]
    private array $dealerCards = [];

    #[ORM\Column]
    private int $betAmount = 0;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_BETTING;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $result = null;

    #[ORM\Column(nullable: true)]
    private ?int $payout = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $finishedAt = null;

    #[ORM\ManyToOne]
    private ?GameSession $gameSession = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getPlayerCards(): array
    {
        return $this->playerCards;
    }

    public function setPlayerCards(array $playerCards): static
    {
        $this->playerCards = $playerCards;
        return $this;
    }

    public function addPlayerCard(string $card): static
    {
        $this->playerCards[] = $card;
        return $this;
    }

    public function getDealerCards(): array
    {
        return $this->dealerCards;
    }

    public function setDealerCards(array $dealerCards): static
    {
        $this->dealerCards = $dealerCards;
        return $this;
    }

    public function addDealerCard(string $card): static
    {
        $this->dealerCards[] = $card;
        return $this;
    }

    public function getBetAmount(): int
    {
        return $this->betAmount;
    }

    public function setBetAmount(int $betAmount): static
    {
        $this->betAmount = $betAmount;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getResult(): ?string
    {
        return $this->result;
    }

    public function setResult(?string $result): static
    {
        $this->result = $result;
        return $this;
    }

    public function getPayout(): ?int
    {
        return $this->payout;
    }

    public function setPayout(?int $payout): static
    {
        $this->payout = $payout;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getFinishedAt(): ?\DateTimeImmutable
    {
        return $this->finishedAt;
    }

    public function setFinishedAt(?\DateTimeImmutable $finishedAt): static
    {
        $this->finishedAt = $finishedAt;
        return $this;
    }

    public function getGameSession(): ?GameSession
    {
        return $this->gameSession;
    }

    public function setGameSession(?GameSession $gameSession): static
    {
        $this->gameSession = $gameSession;
        return $this;
    }
}
