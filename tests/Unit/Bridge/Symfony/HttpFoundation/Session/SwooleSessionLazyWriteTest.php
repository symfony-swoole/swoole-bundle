<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\HttpFoundation\Session;

use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\Session\SwooleSessionStorage;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBag;
use Symfony\Component\HttpFoundation\Session\Storage\MetadataBag;

/**
 * A session is written back only when the request changed it, and otherwise only kept from expiring.
 *
 * Nothing locks a session between its read and its write. Written back unchanged, a request's copy goes over what an
 * overlapping request of the same session stored meanwhile: a form's CSRF token, stored as the page with the form
 * renders and gone by the time the form is posted.
 */
final class SwooleSessionLazyWriteTest extends TestCase
{
    private const string SESSION_ID = 'a1b2c3d4e5f60718293a4b5c6d7e8f90a1b2c3d4e5f60718293a4b5c6d7e8f9';

    public function testASessionTheRequestLeftAsItWasIsTouchedAndNotWritten(): void
    {
        $store = $this->storeWithASession(['luckyNumber' => 7]);
        [$request, $attributes] = $this->requestOf($store);

        $request->start();
        self::assertSame(7, $attributes->get('luckyNumber'));
        $request->save();

        self::assertSame([], $store->writes());
        self::assertSame([self::SESSION_ID], $store->touches());
    }

    /**
     * MetadataBag moves the session's last-used stamp on every request whose second differs from the one stored, so
     * a session read a moment after it was written has always "changed" by it. That alone writes nothing.
     */
    public function testWhenTheSessionWasLastUsedIsNotAChange(): void
    {
        $store = $this->storeWithASession(['luckyNumber' => 7], lastUsed: time() - 600);
        [$request] = $this->requestOf($store);

        $request->start();
        $request->save();

        self::assertSame([], $store->writes());
        self::assertSame([self::SESSION_ID], $store->touches());
    }

    public function testASessionTheRequestChangedIsWritten(): void
    {
        $store = $this->storeWithASession(['luckyNumber' => 7]);
        [$request, $attributes] = $this->requestOf($store);

        $request->start();
        $attributes->set('luckyNumber', 8);
        $request->save();

        self::assertSame([self::SESSION_ID], $store->writes());
        self::assertSame([], $store->touches());
        self::assertSame(8, $this->read($store)->get('luckyNumber'));
    }

    public function testASessionTheStoreDidNotHaveIsWritten(): void
    {
        $store = new InMemoryStorage();
        [$request] = $this->requestOf($store);

        $request->start();
        $request->save();

        self::assertCount(1, $store->writes());
        self::assertSame([], $store->touches());
    }

    /**
     * Signing in migrates the session to a new id, and the new id has nothing stored under it until it is written -
     * though the data it carries over is the data that was read.
     */
    public function testASessionGivenANewIdIsWrittenUnderIt(): void
    {
        $store = $this->storeWithASession(['luckyNumber' => 7]);
        [$request] = $this->requestOf($store);

        $request->start();
        $request->regenerate(true);
        $request->save();

        $newId = $request->getId();
        self::assertNotSame(self::SESSION_ID, $newId);
        self::assertSame([$newId], $store->writes());
        self::assertArrayNotHasKey(self::SESSION_ID, $store->stored());
        self::assertSame(7, $this->read($store, $newId)->get('luckyNumber'));
    }

    public function testASessionSavedAgainUnchangedAfterItsWriteIsTouched(): void
    {
        $store = $this->storeWithASession(['luckyNumber' => 7]);
        [$request, $attributes] = $this->requestOf($store);

        $request->start();
        $attributes->set('luckyNumber', 8);
        $request->save();
        $request->save();

        self::assertSame([self::SESSION_ID], $store->writes());
        self::assertSame([self::SESSION_ID], $store->touches());
    }

    /**
     * The fingerprint leaves the last-used stamp out of a copy of the data, not out of the data the metadata bag
     * holds: a session that is written carries the stamp still.
     */
    public function testAWrittenSessionStillSaysWhenItWasLastUsed(): void
    {
        $store = $this->storeWithASession(['luckyNumber' => 7], lastUsed: time() - 600);
        [$request, $attributes] = $this->requestOf($store);

        $request->start();
        $attributes->set('luckyNumber', 8);
        $request->save();

        $written = unserialize($store->stored()[self::SESSION_ID]);
        self::assertIsArray($written);
        self::assertGreaterThanOrEqual(time() - 1, $written['_sf2_meta'][MetadataBag::UPDATED]);
    }

    /**
     * Two requests of one session: a slow one that changes nothing, and a fast one that stores a CSRF token while
     * the slow one is still running. The slow one finishes last.
     */
    public function testWhatAnOverlappingRequestStoredSurvivesOneThatChangedNothing(): void
    {
        $store = $this->storeWithASession(['luckyNumber' => 7]);
        [$slow] = $this->requestOf($store);
        [$fast, $fastAttributes] = $this->requestOf($store);

        $slow->start();
        $fast->start();
        $fastAttributes->set('_csrf/submit', 'the-token');
        $fast->save();
        $slow->save();

        self::assertSame('the-token', $this->read($store)->get('_csrf/submit'));
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function storeWithASession(array $attributes, ?int $lastUsed = null): InMemoryStorage
    {
        $store = new InMemoryStorage();
        $lastUsed ??= time();
        $store->set(self::SESSION_ID, serialize([
            '_sf2_attributes' => $attributes,
            '_sf2_meta' => [
                MetadataBag::CREATED => $lastUsed,
                MetadataBag::UPDATED => $lastUsed,
                MetadataBag::LIFETIME => 86400,
            ],
        ]), 86400);
        $store->forgetWhatWasAsked();

        return $store;
    }

    /**
     * A request's own storage of the session, as SwooleSessionStorageFactory makes one per request.
     *
     * @return array{SwooleSessionStorage, AttributeBag}
     */
    private function requestOf(InMemoryStorage $store, string $sessionId = self::SESSION_ID): array
    {
        $attributes = new AttributeBag();
        $attributes->setName('attributes');
        $storage = new SwooleSessionStorage($store, SwooleSessionStorage::DEFAULT_SESSION_NAME, 86400, 0);
        $storage->registerBag($attributes);
        $storage->setId($sessionId);

        return [$storage, $attributes];
    }

    private function read(InMemoryStorage $store, string $sessionId = self::SESSION_ID): AttributeBag
    {
        [$storage, $attributes] = $this->requestOf($store, $sessionId);
        $storage->start();

        return $attributes;
    }
}
