<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Mime;

use Egulias\EmailValidator\EmailValidator;
use Override;
use ReflectionProperty;
use SwooleBundle\SwooleBundle\Server\Runtime\Bootable;
use Symfony\Component\Mime\Address;

/**
 * Gives every coroutine its own email validator, by putting a pooled one where {@see Address} looks.
 *
 * Address keeps its validator in a private static, filled with `self::$validator ??= new
 * EmailValidator()` on the first Address anything constructs and reused by every Address after that.
 * The validator writes its verdict onto itself - `$warnings` and `$error` on each isValid() call, into
 * a lexer it built once and drives through every address it reads - so two coroutines addressing mail
 * at the same time write over each other. What that costs is a mail that occasionally goes missing,
 * and nothing anywhere says why.
 *
 * ## Pooled rather than replaced
 *
 * A validator that simply kept nothing would answer isValid() correctly and was tried first. It is the
 * wrong shape twice over: it has to reimplement isValid() and so drifts from the dependency the moment
 * upstream changes it, and it has no honest answer to getWarnings() or getError(), which exist and are
 * part of what an EmailValidator is. Pooling keeps the real class, every line of it, and gives each
 * coroutine one to itself - so the readers answer for the caller reading them.
 *
 * ## Nothing is built here
 *
 * What arrives is a service the bundle has already pooled: the extension registers an EmailValidator
 * and tags it {@see ContainerConstants::TAG_STATEFUL_SERVICE}, which is all it takes for
 * StatefulServicesPass to hand out a pool proxy instead - one that extends EmailValidator, which is
 * what the property's type requires, and forwards each call to the instance belonging to the coroutine
 * making it. The pool, its limit, its mutex and its registration for release are the pass's business
 * rather than this class's, exactly as for every other stateful service.
 *
 * All that is left is putting it where Address will find it, and reflection is the only way in: the
 * property is private static on a final class, and it fills itself on first use - so the only opening
 * is to have filled it first.
 *
 * ## Booted, not injected into anything
 *
 * A bootable service, so this runs in the master before Server::start() forks: every worker inherits a
 * static that already holds the proxy. Installing it per worker would mean writing a process-wide
 * static from a worker, which is the shape this exists to stop.
 *
 * Registered only where coroutines are on. A process doing one thing at a time shares the stock
 * validator perfectly happily.
 *
 * Nothing is caught. A missing property means symfony/mime has moved its validator somewhere else, and
 * the honest answer is a boot that fails loudly rather than a quiet no-op leaving it shared.
 */
final readonly class MimeAddressValidatorInstaller implements Bootable
{
    private const string VALIDATOR_PROPERTY = 'validator';

    /**
     * @param EmailValidator $validator the pooled one - a proxy, wherever coroutines are on
     */
    public function __construct(private EmailValidator $validator) {}

    /**
     * {@inheritDoc}
     *
     * Idempotent, and that is load-bearing rather than tidy: a server booted twice in one process -
     * which the feature tests do - must not write the static a second time while coroutines from the
     * first are still validating through it.
     */
    #[Override]
    public function boot(array $runtimeConfiguration = []): void
    {
        $property = new ReflectionProperty(Address::class, self::VALIDATOR_PROPERTY);

        if ($property->isInitialized() && $property->getValue() === $this->validator) {
            return;
        }

        $property->setValue(null, $this->validator);
    }
}
