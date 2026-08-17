<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\TaskWorker;

use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker\ResolvedCommand;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;

final class ResolvedCommandTest extends TestCase
{
    public function testThatItRunsTheCommandDirectly(): void
    {
        $command = new SignalRecordingCommand([]);
        $resolved = new ResolvedCommand('app:noop', $command, new StringInput(''));

        self::assertSame(0, $resolved->run(new NullOutput()));
        self::assertTrue($command->wasExecuted());
    }

    public function testThatItDeliversSigtermWhenTheCommandSubscribesToIt(): void
    {
        $command = new SignalRecordingCommand([SIGINT, SIGTERM]);
        $resolved = new ResolvedCommand('app:noop', $command, new StringInput(''));

        self::assertTrue($resolved->requestStop());
        // SIGTERM even though SIGINT is listed first: it is the signal a real shutdown would have sent.
        self::assertSame([SIGTERM], $command->handledSignals());
    }

    public function testThatItFallsBackToTheFirstSubscribedSignal(): void
    {
        $command = new SignalRecordingCommand([SIGINT]);
        $resolved = new ResolvedCommand('app:noop', $command, new StringInput(''));

        self::assertTrue($resolved->requestStop());
        self::assertSame([SIGINT], $command->handledSignals());
    }

    public function testThatItReportsWhenNoStopCanBeDelivered(): void
    {
        $command = new SignalRecordingCommand([]);
        $resolved = new ResolvedCommand('app:noop', $command, new StringInput(''));

        // A command subscribing to nothing cannot be asked to stop at all, and the caller has to know
        // that rather than assume the stop landed.
        self::assertFalse($resolved->requestStop());
        self::assertSame([], $command->handledSignals());
    }
}
