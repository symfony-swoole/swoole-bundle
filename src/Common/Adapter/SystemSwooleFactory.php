<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Common\Adapter;

use RuntimeException;
use SwooleBundle\SwooleBundle\Common\System\System;
use Traversable;

final class SystemSwooleFactory implements SwooleFactory
{
    /**
     * @var array<string, SwooleFactory>
     */
    private array $adapterFactories;

    /**
     * @param Traversable<string, SwooleFactory> $adapterFactories
     */
    public function __construct(
        private readonly System $system,
        Traversable $adapterFactories,
    ) {
        $this->adapterFactories = iterator_to_array($adapterFactories);
    }

    public function newInstance(): Swoole
    {
        $extensionString = $this->system->extension()->toString();

        if (!isset($this->adapterFactories[$extensionString])) {
            throw new RuntimeException(sprintf('Adapter factory for extension "%s" not found.', $extensionString));
        }

        return $this->adapterFactories[$extensionString]->newInstance();
    }
}
