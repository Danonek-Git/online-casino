<?php

namespace App\Entity;

use App\Repository\BetRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: BetRepository::class)]
class Bet
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Assert\NotBlank]
    #[ORM\Column(length: 20)]
    private string $betType;

    #[Assert\NotBlank]
    #[ORM\Column(length: 20)]
    private string $betValue;

    #[Assert\Positive]
    #[ORM\Column]
    private int $amount;

    #[ORM\Column(nullable: true)]
    private ?int $payout = null;

    #[ORM\Column(nullable: true)]
    private ?bool $isWin = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $placedAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?GameSession $gameSession = null;

    #[ORM\ManyToOne(inversedBy: 'bets')]
    #[ORM\JoinColumn(nullable: false)]
    private ?RouletteRound $round = null;

    #[ORM\ManyToOne(inversedBy: 'bets')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    public function __construct()
    {
        $this->placedAt = new \DateTimeImmutable();
    }
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBetType(): ?string
    {
        return $this->betType;
    }

    public function setBetType(string $betType): static
    {
        $this->betType = $betType;

        return $this;
    }

    public function getBetValue(): ?string
    {
        return $this->betValue;
    }

    public function setBetValue(string $betValue): static
    {
        $this->betValue = $betValue;

        return $this;
    }

    public function getAmount(): ?int
    {
        return $this->amount;
    }

    public function setAmount(int $amount): static
    {
        $this->amount = $amount;

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

    public function isWin(): ?bool
    {
        return $this->isWin;
    }

    public function setIsWin(?bool $isWin): static
    {
        $this->isWin = $isWin;

        return $this;
    }

    public function getPlacedAt(): ?\DateTimeImmutable
    {
        return $this->placedAt;
    }

    public function setPlacedAt(\DateTimeImmutable $placedAt): static
    {
        $this->placedAt = $placedAt;

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

    public function getRound(): ?RouletteRound
    {
        return $this->round;
    }

    public function setRound(?RouletteRound $round): static
    {
        $this->round = $round;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }
}
