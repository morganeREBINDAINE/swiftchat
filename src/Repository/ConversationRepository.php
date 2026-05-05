<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Conversation;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Conversation>
 */
class ConversationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Conversation::class);
    }

    public function findBetween(User $a, User $b): ?Conversation
    {
        return $this->createQueryBuilder('c')
            ->where(
                '(c.participant1 = :a AND c.participant2 = :b) OR (c.participant1 = :b AND c.participant2 = :a)'
            )
            ->setParameter('a', $a)
            ->setParameter('b', $b)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return Conversation[]
     */
    public function findForUser(User $user, int $limit = 20): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.participant1 = :user OR c.participant2 = :user')
            ->setParameter('user', $user)
            ->orderBy('c.updatedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function save(Conversation $conversation, bool $flush = false): void
    {
        $this->getEntityManager()->persist($conversation);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
