<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * EXPERIMENTAL. A console command resolved and bound, ready to run inside a task worker.
 *
 * Held apart from the running of it so the watchdog has something to stop: once run() is blocking a
 * coroutine, requestStop() is the only way back out.
 */
final readonly class ResolvedCommand
{
    public function __construct(
        public string $commandLine,
        private Command $command,
        private InputInterface $input,
    ) {}

    /**
     * Runs the command directly rather than through Application::run().
     *
     * That is deliberate. Application::doRunCommand() is where console signal handling lives, and
     * inside a swoole worker registering those handlers is pointless - swoole owns the worker signals
     * and the handlers never fire. Going straight to Command::run() skips the registration and leaves
     * requestStop() as the single, working stop path.
     */
    public function run(OutputInterface $output): int
    {
        return $this->command->run($this->input, $output);
    }

    /**
     * Delivers the stop the command would have received as a signal.
     *
     * SignalableCommandInterface is already the contract for "wind down cleanly", so rather than
     * stripping it out of the command this hands it the signal it asked for, from a coroutine instead
     * of from a signal handler. For messenger:consume that reaches Worker::stop() - exactly what a real
     * SIGTERM would have done.
     *
     * @return bool whether a stop could be delivered at all
     */
    public function requestStop(): bool
    {
        $signal = $this->stopSignal();

        if ($signal === null) {
            return false;
        }

        $this->command->handleSignal($signal, 0);

        return true;
    }

    /**
     * Prefers SIGTERM, the signal a shutdown would really have sent. Commands subscribing to a
     * different set (SIGINT only, say) still get told to stop rather than being left running.
     */
    private function stopSignal(): ?int
    {
        $subscribed = $this->command->getSubscribedSignals();

        if ($subscribed === []) {
            return null;
        }

        if (defined('SIGTERM') && in_array(SIGTERM, $subscribed, true)) {
            return SIGTERM;
        }

        return $subscribed[0];
    }
}
