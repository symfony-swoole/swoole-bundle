<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\HttpFoundation\Session\PdoSession;

use ArrayObject;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\Session\PdoSession\PDOAliveKeeper;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\Session\PdoSession\PDOKeepAliveInitializer;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\Session\PdoSession\PDOPlatformAliveKeeper;
use UnexpectedValueException;

/**
 * Who asks the alive keeper, and when: as a pooled connection is handed to a coroutine, or - without coroutines -
 * as the resetter bundle starts a request.
 */
final class PDOKeepAliveTest extends TestCase
{
    public function testAPooledConnectionIsLookedAfterAsItIsHandedOut(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $aliveKeeper = $this->aliveKeeper();

        (new PDOKeepAliveInitializer($aliveKeeper, 'app.session.pdo'))->initialize($pdo);

        $this->assertSame([[$pdo, 'app.session.pdo']], $aliveKeeper->kept);
    }

    public function testAnInitializerOfSomethingElseSaysSo(): void
    {
        $this->expectException(UnexpectedValueException::class);

        (new PDOKeepAliveInitializer($this->aliveKeeper(), 'app.session.pdo'))->initialize(new stdClass());
    }

    public function testEveryConnectionIsLookedAfterAsARequestStarts(): void
    {
        $session = new PDO('sqlite::memory:');
        $other = new PDO('sqlite::memory:');
        $aliveKeeper = $this->aliveKeeper();

        (new PDOPlatformAliveKeeper(
            ['session' => static fn(): PDO => $session, 'other' => static fn(): PDO => $other],
            ['session' => $aliveKeeper, 'other' => $aliveKeeper],
        ))->keepAlive();

        $this->assertSame([[$session, 'session'], [$other, 'other']], $aliveKeeper->kept);
    }

    public function testAConnectionNobodyHasMadeYetIsNotMadeToBeLookedAfter(): void
    {
        $session = new PDO('sqlite::memory:');
        // What the container gives: nothing before something has made the connection, and then the connection.
        /** @var ArrayObject<string, PDO> $made */
        $made = new ArrayObject();
        $aliveKeeper = $this->aliveKeeper();
        $platform = new PDOPlatformAliveKeeper(
            ['session' => static fn(): ?PDO => $made['session'] ?? null],
            ['session' => $aliveKeeper],
        );

        // A request that uses no session: nothing to look after, and nothing opened.
        $platform->keepAlive();
        $this->assertSame([], $aliveKeeper->kept);

        // Once the handler has made its connection, it is looked after from the next request on.
        $made['session'] = $session;
        $platform->keepAlive();
        $this->assertSame([[$session, 'session']], $aliveKeeper->kept);
    }

    public function testAnAliveKeeperWithoutItsConnectionSaysSo(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Connection "session" is missing.');

        (new PDOPlatformAliveKeeper([], ['session' => $this->aliveKeeper()]))->keepAlive();
    }

    /**
     * @return object{kept: list<array{PDO, string}>}&PDOAliveKeeper
     */
    private function aliveKeeper(): PDOAliveKeeper
    {
        return new class implements PDOAliveKeeper {
            /**
             * @var list<array{PDO, string}>
             */
            public array $kept = []; // phpcs:ignore SlevomatCodingStandard.Classes.ForbiddenPublicProperty

            public function keepAlive(PDO $connection, string $connectionName): void
            {
                $this->kept[] = [$connection, $connectionName];
            }
        };
    }
}
