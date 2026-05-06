<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Conversation;
use App\Entity\User;
use App\Repository\ConversationRepository;
use Doctrine\ORM\EntityManagerInterface;

class ConversationService
{
    public function __construct(
        private readonly ConversationRepository $conversationRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * Returns the existing conversation between two users, or creates one.
     *
     * Participants are normalised by UUID order before creation so that the
     * unique constraint on (participant1_id, participant2_id) is never hit by
     * two concurrent calls with the same pair in opposite order.
     */
    public function findOrCreate(User $a, User $b): Conversation
    {
        $conversation = $this->conversationRepository->findBetween($a, $b);

        if ($conversation !== null) {
            return $conversation;
        }

        // Deterministic order: smaller UUID string becomes participant1
        [$p1, $p2] = strcmp($a->getId()->toRfc4122(), $b->getId()->toRfc4122()) < 0
            ? [$a, $b]
            : [$b, $a];

        $conversation = new Conversation($p1, $p2);
        $this->em->persist($conversation);
        $this->em->flush();

        return $conversation;
    }
}
