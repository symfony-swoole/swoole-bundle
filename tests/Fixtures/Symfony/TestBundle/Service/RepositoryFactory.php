<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Service;

final class RepositoryFactory
{
    public function newInstance(): InMemoryRepository
    {
        return new InMemoryRepository();
    }
}
