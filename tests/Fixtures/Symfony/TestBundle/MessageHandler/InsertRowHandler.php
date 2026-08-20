<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\MessageHandler;

use Co;
use Doctrine\ORM\EntityManagerInterface;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Entity\ConsumedMessage;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Message\InsertRow;

/**
 * Writes the row {@see InsertRow} asks for, stamped with who wrote it.
 *
 * Deliberately goes through the ORM rather than sending the insert itself: an EntityManager, its unit
 * of work and the connection under it are exactly the services a group of consumers running side by
 * side in one process would otherwise share, so a handler that reached past them would leave the
 * interesting half of the workload untested.
 *
 * The coroutine id identifies the consumer. Each command of a task worker group runs in a coroutine of
 * its own for the whole of its run, and the handler runs in that same coroutine, so the distinct ids
 * across the rows count the consumers that did any work.
 */
final readonly class InsertRowHandler
{
    public function __construct(private EntityManagerInterface $entityManager) {}

    public function __invoke(InsertRow $message): void
    {
        $sleepMs = $message->sleepMs();

        if ($sleepMs > 0) {
            // Hooked under coroutines, so this yields to the sibling consumers rather than holding the
            // whole task worker.
            usleep($sleepMs * 1000);
        }

        // Read before write, because a handler that only wrote would leave half of what a consumer
        // touches out of the test: a DQL query is parsed, cached and hydrated through services the
        // whole worker shares, where the insert below goes through the entity manager alone.
        $this->entityManager->createQuery(
            'SELECT COUNT(c.id) FROM ' . ConsumedMessage::class . ' c WHERE c.messageId = :messageId',
        )
            ->setParameter('messageId', $message->messageId())
            ->getSingleScalarResult();

        $this->entityManager->persist(new ConsumedMessage(
            $message->messageId(),
            Co::getCid(),
            (int) getmypid(),
        ));
        $this->entityManager->flush();
    }
}
