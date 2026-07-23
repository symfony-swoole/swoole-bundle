<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Configurator;

use Swoole\Http\Server;
use SwooleBundle\SwooleBundle\Server\Session\SwooleTableStorage;

final readonly class WithSwooleTableStorageConfigurator implements Configurator
{
    public function __construct(
        private SwooleTableStorage $storage, // @phpstan-ignore-line
    ) {}

    public function configure(Server $server): void
    {
        // This configurator intentionally does nothing. Its sole purpose is to
        // hold a reference to SwooleTableStorage so that the Symfony DI container
        // eagerly instantiates it (and therefore calls Swoole\Table::create()) in
        // the master process before workers are forked. This ensures the shared
        // memory Table is available across all workers.
    }
}
