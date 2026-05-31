<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Message>
 */
class MessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    /**
     * @return Message[]
     */
    public function findByConversation(Conversation $conversation, int $limit = 50): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.conversation = :conversation')
            ->setParameter('conversation', $conversation)
            ->orderBy('m.createdAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Returns up to $limit messages sent before the message identified by $beforeId,
     * in chronological order (oldest first). Used for cursor-based pagination.
     * Relies on UUID v7 monotonic ordering instead of created_at to avoid ties.
     *
     * @return Message[]
     */
    public function findBeforeId(Conversation $conversation, Uuid $beforeId, int $limit = 50): array
    {
        $results = $this->createQueryBuilder('m')
            ->where('m.conversation = :conversation')
            ->andWhere('m.id < :beforeId')
            ->orderBy('m.id', 'DESC')
            ->setMaxResults($limit)
            ->setParameter('conversation', $conversation)
            ->setParameter('beforeId', $beforeId, 'uuid')
            ->getQuery()
            ->getResult();

        return array_reverse($results);
    }

    public function markAllAsReadBy(Conversation $conversation, User $reader): int
    {
        return (int) $this->createQueryBuilder('m')
            ->update()
            ->set('m.readAt', ':now')
            ->where('m.conversation = :conversation')
            ->andWhere('m.sender != :reader')
            ->andWhere('m.readAt IS NULL')
            ->setParameter('conversation', $conversation)
            ->setParameter('reader', $reader)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->execute();
    }

    /**
     * @param Conversation[] $conversations
     *
     * @return array<string, int> keyed by conversation UUID (RFC4122), default 0
     */
    public function countUnreadPerConversation(array $conversations, User $user): array
    {
        if ([] === $conversations) {
            return [];
        }

        $rows = $this->createQueryBuilder('m')
            ->select('IDENTITY(m.conversation) AS convId, COUNT(m.id) AS cnt')
            ->where('m.conversation IN (:conversations)')
            ->andWhere('m.sender != :user')
            ->andWhere('m.readAt IS NULL')
            ->groupBy('m.conversation')
            ->setParameter('conversations', $conversations)
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();

        $map = [];
        foreach ($conversations as $conv) {
            $map[$conv->getId()->toRfc4122()] = 0;
        }
        foreach ($rows as $row) {
            $map[$row['convId']] = (int) $row['cnt'];
        }

        return $map;
    }

    public function countToday(): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.createdAt >= :today')
            ->setParameter('today', new \DateTimeImmutable('today'))
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countActiveUserIdsToday(): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(DISTINCT IDENTITY(m.sender))')
            ->where('m.createdAt >= :since')
            ->setParameter('since', new \DateTimeImmutable('-24 hours'))
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function save(Message $message, bool $flush = false): void
    {
        $this->getEntityManager()->persist($message);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
