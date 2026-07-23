<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\Session;

use SwooleBundle\SwooleBundle\Server\Session\Storage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Storage\MetadataBag;
use Symfony\Component\HttpFoundation\Session\Storage\SessionStorageFactoryInterface;
use Symfony\Component\HttpFoundation\Session\Storage\SessionStorageInterface;

final readonly class SwooleSessionStorageFactory implements SessionStorageFactoryInterface
{
    /**
     * @param array{cookie_lifetime?: int, gc_probability?: int, gc_divisor?: int} $sessionOptions
     */
    public function __construct(
        private Storage $storage,
        private ?MetadataBag $metadataBag = null,
        private array $sessionOptions = [],
    ) {}

    public function createStorage(?Request $request): SessionStorageInterface
    {
        $lifetimeSeconds = (int) ($this->sessionOptions['cookie_lifetime'] ?? session_get_cookie_params()['lifetime']);
        $gcProbability = $this->sessionOptions['gc_probability'] ?? (int) ini_get('session.gc_probability');
        $gcDivisor = $this->sessionOptions['gc_divisor'] ?? (int) ini_get('session.gc_divisor');

        return new SwooleSessionStorage(
            $this->storage,
            SwooleSessionStorage::DEFAULT_SESSION_NAME,
            $lifetimeSeconds,
            $gcProbability,
            $gcDivisor,
            $this->metadataBag
        );
    }
}
