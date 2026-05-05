<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ConversationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ConversationRepository::class)]
#[ORM\UniqueConstraint(name: 'uq_conversation_participants', columns: ['participant1_id', 'participant2_id'])]
class Conversation
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $participant1;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $participant2;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(User $participant1, User $participant2)
    {
        $this->id = Uuid::v7();
        $this->participant1 = $participant1;
        $this->participant2 = $participant2;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getParticipant1(): User
    {
        return $this->participant1;
    }

    public function getParticipant2(): User
    {
        return $this->participant2;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function hasParticipant(User $user): bool
    {
        return $this->participant1 === $user || $this->participant2 === $user;
    }

    public function getOtherParticipant(User $user): User
    {
        return $this->participant1 === $user ? $this->participant2 : $this->participant1;
    }
}
