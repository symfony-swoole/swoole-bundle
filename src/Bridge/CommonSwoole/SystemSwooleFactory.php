<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\CommonSwoole;

use RuntimeException;
use SwooleBundle\SwooleBundle\Bridge\OpenSwoole\OpenSwooleFactory;
use SwooleBundle\SwooleBundle\Bridge\Swoole\SwooleFactory;
use SwooleBundle\SwooleBundle\Common\Adapter\Swoole;
use SwooleBundle\SwooleBundle\Common\Adapter\SwooleFactory as CommonSwooleFactory;
use SwooleBundle\SwooleBundle\Common\System\Extension;
use SwooleBundle\SwooleBundle\Common\System\System;

final class SystemSwooleFactory implements CommonSwooleFactory
{
    /**
     * @param array<string, CommonSwooleFactory> $adapterFactories
     */
    public function __construct(
        private readonly System $system,
        private readonly array $adapterFactories,
    ) {}

    public function newInstance(): Swoole
    {
        $extensionString = $this->system->extension()->toString();

        if (!isset($this->adapterFactories[$extensionString])) {
            throw new RuntimeException(sprintf('Adapter factory for extension "%s" not found.', $extensionString));
        }

        return $this->adapterFactories[$extensionString]->newInstance();
    }

    public static function newFactoryInstance(): self
    {
        return new self(
            System::create(),
            [
                Extension::SWOOLE => new SwooleFactory(),
                Extension::OPENSWOOLE => new OpenSwooleFactory(),
            ],
        );
    }
}
