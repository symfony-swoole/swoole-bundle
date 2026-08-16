<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\TaskWorker;

use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker\ResolvedCommand;
use Symfony\Component\Console\Command\LazyCommand;
use Symfony\Component\Console\Input\StringInput;

/**
 * The resolver itself needs a booted kernel, so what is pinned here is the part that broke without a
 * kernel in sight: a LazyCommand reports subscribing to no signals, whatever the command inside it says.
 *
 * FrameworkBundle registers commands through a command loader, so this wrapper is what Application::find()
 * returns for practically every command in a real application.
 */
final class ApplicationCommandResolverTest extends TestCase
{
    public function testThatALazyCommandHidesTheSignalsOfTheCommandInsideIt(): void
    {
        $real = new SignalRecordingCommand([SIGTERM]);
        $lazy = new LazyCommand('app:noop', [], '', false, static fn(): SignalRecordingCommand => $real);

        // Left wrapped, the stop would land on nothing and the command would run until it was
        // force-terminated - with a warning claiming it subscribes to no signals when it plainly does.
        self::assertSame([], $lazy->getSubscribedSignals());
        self::assertSame([SIGTERM], $lazy->getCommand()->getSubscribedSignals());
    }

    public function testThatTheUnwrappedCommandCanBeStopped(): void
    {
        $real = new SignalRecordingCommand([SIGTERM]);
        $lazy = new LazyCommand('app:noop', [], '', false, static fn(): SignalRecordingCommand => $real);

        $wrapped = new ResolvedCommand('app:noop', $lazy, new StringInput(''));
        $unwrapped = new ResolvedCommand(
            'app:noop',
            $lazy->getCommand(),
            new StringInput(''),
        );

        self::assertFalse($wrapped->requestStop(), 'A wrapped command silently swallows the stop.');
        self::assertTrue($unwrapped->requestStop());
        self::assertSame([SIGTERM], $real->handledSignals());
    }
}
