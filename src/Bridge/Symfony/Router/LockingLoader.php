<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Router;

use SplFileInfo;
use SwooleBundle\SwooleBundle\Component\Locking\Mutex;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Loader\LoaderResolverInterface;

final class LockingLoader implements LoaderInterface
{
    /** @var array<string, mixed> */
    private array $alreadyLoaded = [];

    /** @var array<string, bool> */
    private array $alreadySupported = [];

    public function __construct(
        private LoaderInterface $decorated,
        private Mutex $mutex,
    ) {}

    public function load(mixed $resource, ?string $type = null): mixed
    {
        $loaderKey = $this->createLoaderKey($resource, $type);

        if (isset($this->alreadyLoaded[$loaderKey])) {
            return $this->alreadyLoaded[$loaderKey];
        }

        try {
            $this->mutex->acquire();

            if (isset($this->alreadyLoaded[$loaderKey])) {
                return $this->alreadyLoaded[$loaderKey];
            }

            $this->alreadyLoaded[$loaderKey] = $this->decorated->load($resource, $type);

            return $this->alreadyLoaded[$loaderKey];
        } finally {
            $this->mutex->release();
        }
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        $loaderKey = $this->createLoaderKey($resource, $type);

        if (isset($this->alreadySupported[$loaderKey])) {
            return $this->alreadySupported[$loaderKey];
        }

        try {
            $this->mutex->acquire();

            if (isset($this->alreadySupported[$loaderKey])) {
                return $this->alreadySupported[$loaderKey];
            }

            $this->alreadySupported[$loaderKey] = $this->decorated->supports($resource, $type);

            return $this->alreadySupported[$loaderKey];
        } finally {
            $this->mutex->release();
        }
    }

    public function getResolver(): LoaderResolverInterface
    {
        return $this->decorated->getResolver();
    }

    public function setResolver(LoaderResolverInterface $resolver): void
    {
        $this->decorated->setResolver($resolver);
    }

    private function createLoaderKey(mixed $resource, ?string $type): string
    {
        return match (true) {
            is_string($resource) => "s:$resource:$type",

            $resource instanceof SplFileInfo =>
                'f:' . $resource->getRealPath() . ':' . $type,

            is_object($resource) =>
                'o:' . spl_object_id($resource) . ':' . $type,

            is_resource($resource) =>
                'r:' . get_resource_id($resource) . ':' . $type,

            default =>
                'v:' . serialize([$resource, $type]),
        };
    }
}
