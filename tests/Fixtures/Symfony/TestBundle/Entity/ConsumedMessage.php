<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * One row per message a messenger consumer handled.
 *
 * Written by {@see \SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\MessageHandler\InsertRowHandler}
 * so that a test can ask the database what a group of consumers actually did, rather than infer it
 * from their output. The message id is the one the sender minted, so the rows say whether every
 * message was handled and whether any was handled twice; the coroutine id and pid say by whom.
 *
 * @final
 */
#[ORM\Entity]
#[ORM\Index(fields: ['messageId'], name: 'message_id_idx')]
#[ORM\Table(name: 'consumed_message')]
class ConsumedMessage // phpcs:ignore SlevomatCodingStandard.Classes.RequireAbstractOrFinal.ClassNeitherAbstractNorFinal
{
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    #[ORM\Id]
    private int $id;

    public function __construct(
        #[ORM\Column(type: 'string', length: 64)]
        private string $messageId,
        #[ORM\Column(type: 'integer')]
        private int $coroutineId,
        #[ORM\Column(type: 'integer')]
        private int $workerPid,
    ) {}

    public function getId(): int
    {
        return $this->id;
    }

    public function getMessageId(): string
    {
        return $this->messageId;
    }

    public function getCoroutineId(): int
    {
        return $this->coroutineId;
    }

    public function getWorkerPid(): int
    {
        return $this->workerPid;
    }
}
