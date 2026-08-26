<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Runtime\Watch;

/**
 * The processes below a pid, read from /proc.
 *
 * A swoole server is a tree - master, manager, workers - and only the master is the process the
 * supervisor started. Signalling the master is therefore not the same as stopping the server: if the
 * master is killed outright, the manager is reparented to init and keeps its workers, and they keep the
 * listen socket. The next server then cannot bind the port.
 *
 * So a supervisor that has to kill has to know what else to kill, and /proc is where that is written.
 * Linux only, which is where swoole runs.
 */
final readonly class ProcessTree
{
    private const string STATE_ZOMBIE = 'Z';

    private const string STATE_DEAD = 'X';

    public function __construct(private string $procDirectory = '/proc') {}

    /**
     * The pid and everything descended from it, children before their own children.
     *
     * The whole table is read once and the tree walked in memory. Asking /proc per process would race
     * against a tree that is in the middle of exiting, and would answer differently depending on how
     * far it had got.
     *
     * @return list<int>
     */
    public function of(int $pid): array
    {
        $childrenByParent = [];

        foreach ($this->parents() as $child => $parent) {
            $childrenByParent[$parent][] = $child;
        }

        $tree = [$pid];

        for ($index = 0; $index < count($tree); $index++) {
            foreach ($childrenByParent[$tree[$index]] ?? [] as $child) {
                // A pid cannot be its own ancestor, but /proc is read from a live system and a
                // malformed table must not turn into a loop that never ends.
                if (in_array($child, $tree, true)) {
                    continue;
                }

                $tree[] = $child;
            }
        }

        return $tree;
    }

    /**
     * Whether the process is still alive in the sense that matters here: able to hold a socket.
     *
     * A zombie is not. It has already terminated and released everything it held; what is left is the
     * entry in the process table, waiting for a parent that may never come to read its exit status. It
     * still has a directory under /proc, so asking the filesystem alone would report a server as still
     * running long after it stopped serving.
     */
    public function isRunning(int $pid): bool
    {
        $state = $this->stateOf($pid);

        return $state !== null && $state !== self::STATE_ZOMBIE && $state !== self::STATE_DEAD;
    }

    /**
     * The single-letter state from /proc/<pid>/stat, or null when there is no such process.
     */
    public function stateOf(int $pid): ?string
    {
        $fields = $this->statFieldsAfterName($pid);

        return $fields[0] ?? null;
    }

    /**
     * A one-line description of the process, for a message that has to say what was left behind.
     */
    public function describe(int $pid): string
    {
        $commandLine = @file_get_contents(sprintf('%s/%d/cmdline', $this->procDirectory, $pid));
        $commandLine = $commandLine === false ? '' : trim(str_replace("\0", ' ', $commandLine));

        return sprintf(
            'pid=%d state=%s %s',
            $pid,
            $this->stateOf($pid) ?? 'gone',
            $commandLine === '' ? '(no command line)' : $commandLine,
        );
    }

    /**
     * @param list<int> $pids
     * @return list<int> those that were still running and have been signalled
     */
    public function kill(array $pids, int $signal): array
    {
        $signalled = [];

        // Deepest first, so a manager cannot notice a dead worker and replace it while the rest of the
        // tree is still being taken down.
        foreach (array_reverse($pids) as $pid) {
            if (!$this->isRunning($pid)) {
                continue;
            }

            if (function_exists('posix_kill')) {
                @posix_kill($pid, $signal);
            }

            $signalled[] = $pid;
        }

        return $signalled;
    }

    /**
     * Every process on the system, as child pid => parent pid.
     *
     * @return array<int, int>
     */
    private function parents(): array
    {
        $entries = @scandir($this->procDirectory);

        if ($entries === false) {
            return [];
        }

        $parents = [];

        foreach ($entries as $entry) {
            if (!ctype_digit($entry)) {
                continue;
            }

            $parent = $this->parentOf((int) $entry);

            if ($parent === null) {
                continue;
            }

            $parents[(int) $entry] = $parent;
        }

        return $parents;
    }

    /**
     * The fourth field of /proc/<pid>/stat is the parent pid. The second is the executable name in
     * brackets, and it can contain spaces and brackets of its own, so the fields are counted from the
     * last closing bracket rather than from the start of the line.
     */
    private function parentOf(int $pid): ?int
    {
        $fields = $this->statFieldsAfterName($pid);

        if (!isset($fields[1]) || !ctype_digit($fields[1])) {
            return null;
        }

        return (int) $fields[1];
    }

    /**
     * The fields of /proc/<pid>/stat after the executable name, so state is [0] and the parent pid is
     * [1].
     *
     * Counted from the last closing bracket rather than from the start of the line: the name is in
     * brackets and can contain spaces and brackets of its own.
     *
     * @return list<string>
     */
    private function statFieldsAfterName(int $pid): array
    {
        $stat = @file_get_contents(sprintf('%s/%d/stat', $this->procDirectory, $pid));

        if ($stat === false) {
            return [];
        }

        $afterName = strrpos($stat, ')');

        if ($afterName === false) {
            return [];
        }

        $fields = preg_split('/\s+/', trim(substr($stat, $afterName + 1)));

        return $fields === false ? [] : $fields;
    }
}
