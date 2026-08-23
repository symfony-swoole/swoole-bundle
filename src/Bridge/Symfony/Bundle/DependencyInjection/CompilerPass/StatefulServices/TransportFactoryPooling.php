<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices;

use ReflectionClass;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\ContainerConstants;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Whether a transport factory can be pooled, as what, and when not, why not.
 *
 * Messenger and Mailer arrived at the same arrangement independently: a transport is not a service but
 * something a tagged factory builds, and the transport keeps the state of one conversation - a
 * consumer's progress through a queue, a session on an SMTP connection - on itself. Neither survives
 * being shared by two coroutines, and in both the way in is the factory rather than what it built.
 *
 * The rules for deciding that are the same for both, and they are the fiddly part - what a factory is
 * allowed to be, what the class it builds is allowed to be, and which of the questions may load a class
 * to ask. Held here once so that a correction reaches both, while what each component says about its
 * own transports stays with it.
 *
 * @see \SwooleBundle\SwooleBundle\Bridge\Symfony\Messenger\MessengerProcessor
 * @see \SwooleBundle\SwooleBundle\Bridge\Symfony\Mailer\MailerProcessor
 */
final readonly class TransportFactoryPooling
{
    /**
     * The suffix a factory's name ends in, and what is taken off it to name the transport beside it.
     */
    private const string FACTORY_SUFFIX = 'Factory';

    /**
     * @param class-string $transportInterface what the class beside the factory has to be, for the name
     *                                         to have been read off the right thing
     * @param list<class-string> $leaveShared factories that are meant to stay shared, and are passed
     *                                        over without a word about it
     */
    public function __construct(
        private string $transportInterface,
        private array $leaveShared = [],
    ) {}

    /**
     * Says why on the way out, for the reasons a developer could act on. The one that is nobody's
     * problem stays quiet: a transport on the {@see self::$leaveShared} list is shared on purpose, and
     * a reason given for it would be noise in front of the ones that are not.
     */
    public function verdictFor(Definition $definition): TransportPoolingVerdict
    {
        $factoryClass = $definition->getClass();

        if ($factoryClass === null || !class_exists($factoryClass)) {
            return TransportPoolingVerdict::leftShared('its service definition names no class to read');
        }

        if (in_array($factoryClass, $this->leaveShared, true)) {
            return TransportPoolingVerdict::sharedOnPurpose();
        }

        if (!str_ends_with($factoryClass, self::FACTORY_SUFFIX)) {
            return TransportPoolingVerdict::leftShared(sprintf(
                'its name does not end in "%s", so there is no telling what it builds',
                self::FACTORY_SUFFIX,
            ));
        }

        $transportClass = mb_substr($factoryClass, 0, -mb_strlen(self::FACTORY_SUFFIX));

        if (!class_exists($transportClass) || !is_a($transportClass, $this->transportInterface, true)) {
            return TransportPoolingVerdict::leftShared(sprintf(
                'there is no transport class "%s" beside it to say what it builds - name the factory '
                . 'after its transport, or tag it "%s" with the returnType spelled out. Nothing to do '
                . 'if it builds no transport of its own and only returns what another factory built, '
                . 'since that one has a pool of its own',
                $transportClass,
                ContainerConstants::TAG_UNMANAGED_FACTORY,
            ));
        }

        $reflection = new ReflectionClass($transportClass);

        // What the proxy generator needs of the transport it will stand in for. None of the three is
        // worth failing the whole compile over, so the factory simply stays shared.
        if ($reflection->isFinal() || $reflection->isAbstract() || $reflection->isReadOnly()) {
            return TransportPoolingVerdict::leftShared(sprintf(
                'the transport it builds, "%s", cannot be extended to stand in for',
                $transportClass,
            ));
        }

        // Wrapping a factory means generating a proxy that extends it. A final one is dealt with - the
        // modification processor un-finals it - but a read-only class cannot be extended at all, and
        // the Proxifier refuses it outright rather than producing something broken.
        //
        // Asked last, and about the factory rather than the transport, because a factory that names no
        // transport of its own has already been answered above with something more useful to read. A
        // dispatching factory - one that picks another tagged factory by DSN and returns what that one
        // built - is read-only as often as not, and reporting it here would be wrong twice over: it
        // builds no transport to share, and the factory it delegates to has a pool of its own.
        $factoryReflection = new ReflectionClass($factoryClass);

        if ($factoryReflection->isReadOnly()) {
            return TransportPoolingVerdict::leftShared(
                'it is a read-only class, which cannot be wrapped to hand out pooled transports',
            );
        }

        return TransportPoolingVerdict::pooledAs($transportClass);
    }
}
