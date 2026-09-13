<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Mercure;

use Override;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;
use Symfony\Component\Mercure\ProtocolVersion;
use Symfony\Component\Mercure\Update;

/**
 * A hub that accepts every update and sends it nowhere.
 *
 * Stands in for the real one underneath MercureBundle's TraceableHub, so the decorator can be exercised
 * without a hub running and without MercureBundle in the fixture app. It keeps nothing between publishes
 * on purpose - the state under test is the decorator's, and a hub with state of its own would muddy what
 * a trace mixing two coroutines' updates means.
 */
final class NullHub implements HubInterface
{
    #[Override]
    public function getPublicUrl(): string
    {
        return 'http://localhost/.well-known/mercure';
    }

    #[Override]
    public function getFactory(): ?TokenFactoryInterface
    {
        return null;
    }

    #[Override]
    public function publish(Update $update): string
    {
        return sprintf('urn:uuid:null-hub-%d', spl_object_id($update));
    }

    #[Override]
    public function getProtocolVersion(): ProtocolVersion
    {
        return ProtocolVersion::Legacy;
    }

    #[Override]
    public function getCookieName(): string
    {
        return ProtocolVersion::Legacy->getDefaultCookieName();
    }
}
