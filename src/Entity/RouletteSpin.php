<?php

namespace App\Entity;

use App\Repository\RouletteSpinRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RouletteSpinRepository::class)]
class RouletteSpin
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $resultNumber = null;

    #[ORM\Column(length: 10)]
    private ?string $resultColor = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $spunAt = null;

    #[ORM\OneToOne(inversedBy: 'rouletteSpin', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?GameSession $gameSession = null;

    public function __construct()
    {
        $this->spunAt = new \DateTimeImmutable();
    }
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getResultNumber(): ?int
    {
        return $this->resultNumber;
    }

    public function setResultNumber(int $resultNumber): static
    {
        $this->resultNumber = $resultNumber;

        return $this;
    }

    public function getResultColor(): ?string
    {
        return $this->resultColor;
    }

    public function setResultColor(string $resultColor): static
    {
        $this->resultColor = $resultColor;

        return $this;
    }

    public function getSpunAt(): ?\DateTimeImmutable
    {
        return $this->spunAt;
    }

    public function setSpunAt(\DateTimeImmutable $spunAt): static
    {
        $this->spunAt = $spunAt;

        return $this;
    }

    public function getGameSession(): ?GameSession
    {
        return $this->gameSession;
    }

    public function setGameSession(GameSession $gameSession): static
    {
        $this->gameSession = $gameSession;

        return $this;
    }
}
