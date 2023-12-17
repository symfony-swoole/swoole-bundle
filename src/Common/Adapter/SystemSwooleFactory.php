<?php

declare(strict_types=1);

namespace K911\Swoole\Common\Adapter;

use K911\Swoole\Common\System\System;

final class SystemSwooleFactory implements SwooleFactory
{
    /**
     * @var array<string, SwooleFactory>
     */
    private array $adapterFactories;

    /**
     * @param \Traversable<string, SwooleFactory> $adapterFactories
     */
    public function __construct(
        private System $system,
        \Traversable $adapterFactories,
    ) {
        $this->adapterFactories = \iterator_to_array($adapterFactories);
    }

    public function newInstance(): Swoole
    {
        $extensionString = $this->system->extension()->toString();

        if (!isset($this->adapterFactories[$extensionString])) {
            throw new \RuntimeException(\sprintf('Adapter factory for extension "%s" not found.', $extensionString));
        }

        return $this->adapterFactories[$extensionString]->newInstance();
    }
}
