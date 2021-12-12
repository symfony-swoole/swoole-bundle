<?php

declare(strict_types=1);

namespace K911\Swoole\Tests\Fixtures\Symfony\TestBundle\Service;

final class RepositoryFactory
{
    public function newInstance(): InMemoryRepository
    {
        return new InMemoryRepository();
    }
}
