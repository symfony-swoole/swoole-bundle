<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\Session;

use Assert\Assertion;
use Assert\AssertionFailedException;
use Exception;
use InvalidArgumentException;
use LogicException as CoreLogicException;
use SwooleBundle\SwooleBundle\Server\Session\Exception\LogicException;
use SwooleBundle\SwooleBundle\Server\Session\Exception\RuntimeException;
use SwooleBundle\SwooleBundle\Server\Session\Storage;
use Symfony\Component\HttpFoundation\Session\SessionBagInterface;
use Symfony\Component\HttpFoundation\Session\Storage\MetadataBag;
use Symfony\Component\HttpFoundation\Session\Storage\SessionStorageInterface;

/**
 * Session storage backed by one of the bundle's own stores rather than by PHP's native session handling.
 *
 * Session data is written with serialize(), which is what PHP's own handling does and what the rest of
 * Symfony assumes: a session bag holds whatever the application puts in it, objects included. The
 * security component relies on that outright - a failed login leaves the AuthenticationException in the
 * session for the login page to render:
 *
 * ```php
 * $authenticationException = $session->get(SecurityRequestAttributes::AUTHENTICATION_ERROR);
 * ```
 *
 * Storing the data as JSON instead reads as an equivalent choice and is not one. json_encode() turns an
 * object into its public properties - none, for an exception - and json_decode() hands back an array, so
 * the write succeeds, the read succeeds, and the caller is given something of the wrong type with no
 * error anywhere in between. What it costs is a TypeError in whichever piece of Symfony reads it next.
 */
final class SwooleSessionStorage implements SessionStorageInterface
{
    public const string DEFAULT_SESSION_NAME = 'SWOOLESSID';

    private string $currentId = '';

    /**
     * @var array<SessionBagInterface>
     */
    private array $bags = [];

    /**
     * @var array<string, mixed>
     */
    private array $data = [];

    private MetadataBag $metadataBag;

    private bool $started = false;

    private int $sessionLifetimeSeconds;

    public function __construct(
        private readonly Storage $storage,
        private string $name = self::DEFAULT_SESSION_NAME,
        int $lifetimeSeconds = 86400,
        private int $gcProbability = 1,
        private int $gcDivisor = 100,
        ?MetadataBag $metadataBag = null,
    ) {
        $this->setLifetimeSeconds($lifetimeSeconds);
        $this->setMetadataBag($metadataBag);
    }

    /**
     * @throws AssertionFailedException
     * @throws Exception
     */
    public function start(): bool
    {
        if ($this->started) {
            return true;
        }

        if (empty($this->currentId)) {
            $this->currentId = $this->generateId();
        }

        $this->data = $this->obtainSessionData($this->currentId);
        $this->bindBagsToData($this->data);

        $this->started = true;

        return true;
    }

    /**
     * @throws Exception
     * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
     */
    public function regenerate(bool $destroy = false, ?int $lifetime = null): bool
    {
        if ($destroy) {
            $this->storage->delete($this->currentId);
        }

        if (!headers_sent() && $lifetime !== null && $lifetime !== (int) ini_get('session.cookie_lifetime')) {
            ini_set('session.cookie_lifetime', (string) $lifetime);
        }

        $this->getMetadataBag()->stampNew($lifetime ?? $this->sessionLifetimeSeconds);
        $this->currentId = $this->generateId();

        return true;
    }

    public function save(): void
    {
        if (!$this->started) {
            throw new RuntimeException('Trying to save a session that was not started yet or was already closed');
        }

        $this->storage->set(
            $this->currentId,
            serialize($this->data),
            $this->sessionLifetimeSeconds
        );

        // Probabilistic garbage collection: mirrors PHP's session.gc_probability / session.gc_divisor mechanism.
        // SwooleTableStorage does not use PHP's native session handler so gc() is never called automatically.
        if (
            $this->gcProbability <= 0
            || $this->gcDivisor <= 0
            || random_int(1, $this->gcDivisor) > $this->gcProbability
        ) {
            return;
        }

        $this->storage->garbageCollect();
    }

    public function reset(): void
    {
        foreach ($this->allBags() as $bag) {
            $bag->clear();
        }

        $this->started = false;
        $this->currentId = '';
        $this->data = [];
    }

    public function clear(): void
    {
        $this->storage->delete($this->currentId);
        $this->reset();
    }

    public function isStarted(): bool
    {
        return $this->started;
    }

    public function getId(): string
    {
        return $this->isStarted() ? $this->currentId : '';
    }

    /**
     * @throws Exception
     */
    public function setId(string $id): void
    {
        if ($this->started) {
            throw new LogicException('Cannot set session ID after the session has started.');
        }

        $this->currentId = preg_match('/^[a-f0-9]{63}$/', $id) ? $id : $this->generateId();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * @throws AssertionFailedException
     */
    public function getBag(string $name): SessionBagInterface
    {
        if (!isset($this->bags[$name])) {
            throw new InvalidArgumentException(sprintf('The SessionBagInterface `%s` is not registered.', $name));
        }

        if (!$this->started) {
            $this->start();
        }

        return $this->bags[$name];
    }

    public function registerBag(SessionBagInterface $bag): void
    {
        if ($this->started) {
            throw new CoreLogicException('Cannot register a bag when the session is already started.');
        }

        $this->bags[$bag->getName()] = $bag;
    }

    public function getMetadataBag(): MetadataBag
    {
        return $this->metadataBag;
    }

    private function setLifetimeSeconds(int $lifetimeSeconds): void
    {
        $this->sessionLifetimeSeconds = $lifetimeSeconds;

        if (
            headers_sent()
            || !is_string(ini_get('session.cookie_lifetime'))
            || $lifetimeSeconds === (int) ini_get('session.cookie_lifetime')
        ) {
            return;
        }

        ini_set('session.cookie_lifetime', (string) $lifetimeSeconds);
    }

    /**
     * @return array<string, mixed>
     * @throws AssertionFailedException
     */
    private function obtainSessionData(string $sessionId): array
    {
        $sessionData = $this->storage->get($sessionId, function (): void {
            $this->regenerate(true);
        });

        if ($sessionData === null) {
            return [];
        }

        Assertion::string($sessionData);

        // Sessions stored by a version of this bundle that wrote them as JSON. There is nothing in them
        // worth reading back: JSON cannot carry an object, so whatever the application put in the
        // session was flattened on the way out and would come back as the plain array that its reader
        // has no idea what to do with. Discarding them costs the session and nothing more.
        if (!str_starts_with($sessionData, 'a:')) {
            return [];
        }

        // The session belongs to the application, and only a session id ever reaches it from outside -
        // the same trust PHP's own session handling places in its store. Restricting the classes here
        // would not harden anything; it would only turn the objects the application stored into
        // __PHP_Incomplete_Class on the way back.
        /** @var array<string, mixed> $decoded */
        $decoded = unserialize($sessionData, ['allowed_classes' => true]);
        // @phpstan-ignore-next-line
        Assertion::isArray($decoded, 'Session data is not readable: expected a serialized array.');

        return $decoded;
    }

    /**
     * @return iterable<SessionBagInterface>
     */
    private function allBags(): iterable
    {
        yield from $this->bags;
        yield $this->metadataBag;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function bindBagsToData(array &$data): void // phpcs:ignore
    {
        foreach ($this->allBags() as $bag) {
            $key = $bag->getStorageKey();
            $data[$key] ??= [];
            Assertion::isArray($data[$key]);
            $bag->initialize($data[$key]);
        }
    }

    /**
     * Generates a session ID.
     *
     * @throws Exception
     */
    private function generateId(): string
    {
        return mb_substr(bin2hex(random_bytes(32)), random_int(0, 1), 63);
    }

    private function setMetadataBag(?MetadataBag $metadataBag = null): void
    {
        if (!$metadataBag instanceof MetadataBag) {
            $metadataBag = new MetadataBag();
        }

        $this->metadataBag = $metadataBag;
    }
}
