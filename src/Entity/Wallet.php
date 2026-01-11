<?php

namespace App\Entity;

use App\Entity\User;
use App\Repository\WalletRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: WalletRepository::class)]
class Wallet
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;
    
    #[Assert\GreaterThanOrEqual(0)]
    #[ORM\Column]
    private int $balance = 0;
    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;
    #[ORM\OneToOne(inversedBy: 'wallet')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBalance(): ?int
    {
        return $this->balance;
    }

    public function setBalance(int $balance): static
    {
        if ($balance < 0) {
            throw new \InvalidArgumentException('Saldo nie może być ujemne.');
        }
        $this->balance = $balance;

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
    public function getUser(): ?User
    {
        return $this->user;
    }
    public function setUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }
    public static function createForUser(User $user, int $startingBalance = 1000): self
    {
        $wallet = new self();
        $wallet->setUser($user);
        $wallet->setBalance($startingBalance);
        return $wallet;
    }
}
