<?php

namespace App\Entity;

use App\Repository\RouletteRoundRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RouletteRoundRepository::class)]
class RouletteRound
{
    public const STATUS_OPEN = 'open';
    public const STATUS_FINISHED = 'finished';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $endsAt = null;

    #[ORM\Column(length: 20)]
    private ?string $status = null;

    #[ORM\Column(nullable: true)]
    private ?int $resultNumber = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $resultColor = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $resolvedAt = null;

    /**
     * @var Collection<int, Bet>
     */
    #[ORM\OneToMany(targetEntity: Bet::class, mappedBy: 'round')]
    private Collection $bets;

    public function __construct()
    {
        $this->bets = new ArrayCollection();
        $this->status = self::STATUS_OPEN;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStartedAt(): ?\DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function setStartedAt(\DateTimeImmutable $startedAt): static
    {
        $this->startedAt = $startedAt;

        return $this;
    }

    public function getEndsAt(): ?\DateTimeImmutable
    {
        return $this->endsAt;
    }

    public function setEndsAt(\DateTimeImmutable $endsAt): static
    {
        $this->endsAt = $endsAt;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getResultNumber(): ?int
    {
        return $this->resultNumber;
    }

    public function setResultNumber(?int $resultNumber): static
    {
        $this->resultNumber = $resultNumber;

        return $this;
    }

    public function getResultColor(): ?string
    {
        return $this->resultColor;
    }

    public function setResultColor(?string $resultColor): static
    {
        $this->resultColor = $resultColor;

        return $this;
    }

    public function getResolvedAt(): ?\DateTimeImmutable
    {
        return $this->resolvedAt;
    }

    public function setResolvedAt(?\DateTimeImmutable $resolvedAt): static
    {
        $this->resolvedAt = $resolvedAt;

        return $this;
    }

    /**
     * @return Collection<int, Bet>
     */
    public function getBets(): Collection
    {
        return $this->bets;
    }

    public function addBet(Bet $bet): static
    {
        if (!$this->bets->contains($bet)) {
            $this->bets->add($bet);
            $bet->setRound($this);
        }

        return $this;
    }

    public function removeBet(Bet $bet): static
    {
        if ($this->bets->removeElement($bet)) {
            if ($bet->getRound() === $this) {
                $bet->setRound(null);
            }
        }

        return $this;
    }
}
