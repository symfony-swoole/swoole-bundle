<?php

/**
 * The queues of a real application, with one queue served by a whole group of consumers.
 *
 * The transports are the ones an application of this shape configures - a work queue, a scheduling
 * queue, a synchronous outbox and a failure transport - and all four consumers of the group receive
 * through the single `default` transport (see task_worker.commands in swoole.php).
 *
 * One transport for four consumers is only safe because the bundle pools it, and this environment is
 * here to hold it to that. A transport keeps per-receive state on itself - DoctrineTransport memoizes
 * the receiver it hands out, so left shared, all four consumers would poll through one DoctrineReceiver
 * and one Connection and write over each other's bookkeeping. MessengerProcessor gives each coroutine
 * an instance of its own; see it for what that bookkeeping is and what sharing it costs.
 *
 * The queue underneath is what they share, and it is built for that: the doctrine transport reads with
 * SELECT ... FOR UPDATE SKIP LOCKED, so a row goes to exactly one consumer.
 */

declare(strict_types=1);

use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Command\EnqueueInsertRowsCommand;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Command\MessengerTransportReportCommand;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Message\InsertRow;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\MessageHandler\InsertRowHandler;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $workQueue = [
        'dsn' => 'doctrine://default?queue_name=default',
        // Nothing here is expected to fail, and a retry would hide it if something did: a handler that
        // lost a race would be handed the message again, would probably succeed the second time, and
        // the run would end with every row written and nothing to show for the failure. Straight to the
        // failure transport instead, where the test looks.
        'retry_strategy' => [
            'max_retries' => 0,
        ],
    ];

    $containerConfigurator->extension('framework', [
        'messenger' => [
            'enabled' => true,
            'failure_transport' => 'failed',
            'transports' => [
                'default' => $workQueue,
                'scheduling' => 'doctrine://default?queue_name=scheduling',
                'outbox' => 'sync://',
                'failed' => 'doctrine://default?queue_name=failed',
            ],
            'routing' => [
                InsertRow::class => 'default',
            ],
        ],
    ]);

    $services = $containerConfigurator->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure();

    $services->set(InsertRowHandler::class)
        ->tag('messenger.message_handler');

    $services->set(EnqueueInsertRowsCommand::class);

    $services->set(MessengerTransportReportCommand::class)
        ->arg('$transport', service('messenger.transport.default'));
};
