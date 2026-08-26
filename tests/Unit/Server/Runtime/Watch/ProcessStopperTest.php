<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Server\Runtime\Watch;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Server\Runtime\Watch\ProcessStopper;
use SwooleBundle\SwooleBundle\Server\Runtime\Watch\ProcessTree;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * Stopping a process that will not stop, and what happens to what it started.
 *
 * A swoole server is the case this exists for: master, manager, workers, of which only the master was
 * started by the supervisor. Killing the master alone reparents the manager to init and leaves it
 * holding the listen socket, so the next server cannot bind the port.
 *
 * A real swoole server is not what this test uses. Reproducing the case there means killing a server
 * at the exact moment its workers are busy - slow to set up, and it did not reproduce reliably. The
 * behaviour under test is not swoole's, it is the stopper's: a process that ignores SIGTERM and has a
 * child of its own is the same shape and takes a second.
 *
 * @see ProcessStopper
 */
#[CoversClass(ProcessStopper::class)]
final class ProcessStopperTest extends TestCase
{
    private string $directory;

    /**
     * @var list<Process>
     */
    private array $started = [];

    #[Override]
    protected function setUp(): void
    {
        if (!function_exists('pcntl_fork') || !function_exists('posix_kill')) {
            self::markTestSkipped('ext-pcntl and ext-posix are required to build a process that refuses to stop.');
        }

        $this->directory = sys_get_temp_dir() . '/swoole-bundle-stopper-' . bin2hex(random_bytes(6));
        (new Filesystem())->mkdir($this->directory);
    }

    #[Override]
    protected function tearDown(): void
    {
        // Nothing this test started may outlive it, whatever the assertions did.
        foreach ($this->started as $process) {
            $pid = $process->getPid();

            if ($pid !== null) {
                (new ProcessTree())->kill((new ProcessTree())->of($pid), 9);
            }

            $process->stop(1.0);
        }

        (new Filesystem())->remove($this->directory);
    }

    public function testThatAProcessWhichStopsOnItsOwnIsNotKilled(): void
    {
        $process = $this->startTree(ignoresTermination: false);

        $killed = (new ProcessStopper())->stop($process, 5.0);

        self::assertSame([], $killed, 'It stopped when it was asked to, so nothing needed killing.');
    }

    /**
     * The case the supervisor used to get wrong: the parent is killed, and its child - reparented to
     * init - keeps whatever the parent was holding.
     */
    public function testThatTheChildOfAProcessThatHadToBeKilledIsKilledToo(): void
    {
        $process = $this->startTree(ignoresTermination: true);
        $pid = $process->getPid();
        self::assertIsInt($pid);

        $tree = new ProcessTree();
        $children = array_values(array_filter($tree->of($pid), static fn(int $each): bool => $each !== $pid));
        self::assertNotSame([], $children, 'The fixture did not produce a child to be left behind.');

        $killed = (new ProcessStopper())->stop($process, 1.0);

        self::assertNotSame([], $killed, 'A process ignoring SIGTERM has to be killed.');
        self::assertSame(
            [],
            array_values(array_filter($children, $tree->isRunning(...))),
            'The child outlived the stop. In a swoole server that child is the manager, and it keeps '
            . 'the listen socket the next server needs.',
        );
    }

    /**
     * Builds a parent with one child. The parent optionally ignores SIGTERM, which is what makes
     * Process::stop() fall through to SIGKILL - the signal the parent cannot pass on.
     */
    private function startTree(bool $ignoresTermination): Process
    {
        $script = sprintf('%s/tree-%s.php', $this->directory, $ignoresTermination ? 'stubborn' : 'wellbehaved');

        // Well behaved is what a swoole master does: on SIGTERM it takes its children with it. Stubborn
        // ignores the signal, so Process::stop() falls through to the SIGKILL it cannot pass on.
        $onTermination = $ignoresTermination
            ? 'static function (): void {}'
            : 'static function () use ($child): void { posix_kill($child, SIGKILL); exit(0); }';

        file_put_contents($script, <<<PHP
            <?php
            \$child = pcntl_fork();

            if (\$child === 0) {
                sleep(120);
                exit(0);
            }

            pcntl_async_signals(true);
            pcntl_signal(SIGTERM, {$onTermination});
            sleep(120);
            PHP);

        $process = new Process([PHP_BINARY, $script]);
        $process->setTimeout(null);
        $process->start();
        $this->started[] = $process;

        $this->awaitChild($process);

        return $process;
    }

    private function awaitChild(Process $process): void
    {
        $tree = new ProcessTree();
        $deadline = microtime(true) + 5.0;

        while (microtime(true) < $deadline) {
            $pid = $process->getPid();

            if ($pid !== null && count($tree->of($pid)) > 1) {
                return;
            }

            usleep(50_000);
        }

        self::fail('The fixture process never forked a child.');
    }
}
