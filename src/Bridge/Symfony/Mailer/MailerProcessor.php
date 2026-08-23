<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Mailer;

use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices\{
    CompileProcessor,
    ServiceProxifier,
    TransportFactoryPooling,
};
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServicesPass;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\ContainerConstants;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Mailer\Transport\NullTransportFactory;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * Gives every coroutine a mail transport of its own, so two sends cannot interleave on one connection.
 *
 * A mail transport is one of the most thoroughly stateful services a Symfony application has, and the
 * least obviously so, because for a process that sends one mail at a time it behaves perfectly. An SMTP
 * transport holds an open socket to the mail server and, on top of it, everything about the
 * conversation being had over that socket: whether the session has been started, how many messages it
 * has carried against the restart threshold, when the last one went out, and the buffer of what the
 * server has said back.
 *
 * SMTP is a dialogue - EHLO, MAIL FROM, RCPT TO, DATA, the body, then a dot on a line of its own - and
 * every line of it is written to the same socket and read back in order. Two coroutines sending at once
 * through one transport interleave their halves of that dialogue: one's RCPT TO arrives between
 * another's DATA and its body, and the replies come back to whichever is reading. What comes of it is
 * anything from a rejected message to a mail delivered to the wrong recipient, and it is not
 * deterministic, so it surfaces as mail that is occasionally missing.
 *
 * Nothing reports it either. What the transport shares is a socket and the properties tracking the
 * session over it, and neither raises so much as a warning when two coroutines reach for them at once -
 * the send returns, the mail is accepted or it is not, and where it went is decided by the order two
 * dialogues happened to interleave in.
 *
 * ## The factory is pooled, not the transport
 *
 * Exactly as for messenger, and for the same reason: there is no transport service to pool.
 * `mailer.transports` is a final Transports built once by `mailer.transport_factory::fromStrings()`,
 * and the transports it holds are objects inside it rather than services of their own. What can be
 * reached is the factory each of them came out of, which FrameworkExtension tags
 * `mailer.transport_factory` - so the factory is wrapped, its create() hands back a pool proxy, and
 * the Transports built around those proxies stays shared and correct, because a proxy resolves per
 * coroutine on every call through it.
 *
 * A mailer factory builds with `create()` where a messenger one builds with `createTransport()`. The
 * rest of the decision - the name convention, what the class beside it has to be, what may be extended
 * - is the same, and is {@see TransportFactoryPooling}.
 *
 * ## What is left shared, and what that costs
 *
 * A factory whose name does not say what it builds is left alone with a line in the build log. Of the
 * ones Symfony ships, that is `NativeTransportFactory`, which has no NativeTransport beside it because
 * it builds a sendmail or an SMTP transport depending on what php.ini's sendmail_path says. Third-party
 * bridges are usually in the same position - a MailgunTransportFactory picks between its api, http and
 * smtp transports - and for those the log line is worth acting on, since an API transport left shared
 * still writes $lastSent from every coroutine that sends. Tagging the factory
 * {@see ContainerConstants::TAG_UNMANAGED_FACTORY} by hand, with the returnType spelled out, is the way
 * out where the DSN in use always resolves to one class.
 *
 * @see \SwooleBundle\SwooleBundle\Bridge\Symfony\Messenger\MessengerProcessor for the same shape
 */
final class MailerProcessor implements CompileProcessor
{
    /**
     * The tag FrameworkExtension puts on every mailer transport factory.
     */
    private const string TRANSPORT_FACTORY_TAG = 'mailer.transport_factory';

    /**
     * The method a mailer transport factory builds transports with.
     */
    private const string TRANSPORT_FACTORY_METHOD = 'create';

    /**
     * Transports there is no point giving anyone an instance of their own.
     *
     * A null transport accepts a message and drops it. It is also final, so trying would have earned a
     * line in the build log about a class that cannot be extended - which would be true, and would send
     * a developer looking into a transport that has nothing to share.
     */
    private const array TRANSPORT_FACTORIES_TO_LEAVE_SHARED = [
        NullTransportFactory::class,
    ];

    public function process(ContainerBuilder $container, ServiceProxifier $proxifier): void
    {
        // The pass this processor runs under, and only ever used to name the lines below: the compiler
        // prefixes each with the class of the pass a message came from, and MailerProcessor cannot be
        // that pass itself - CompileProcessor and CompilerPassInterface both declare process(), with
        // different signatures. A fresh instance is enough, since the class is all that is read off it.
        $log = new StatefulServicesPass();
        $pooling = new TransportFactoryPooling(TransportInterface::class, self::TRANSPORT_FACTORIES_TO_LEAVE_SHARED);

        foreach (array_keys($container->findTaggedServiceIds(self::TRANSPORT_FACTORY_TAG)) as $factoryId) {
            $definition = $container->findDefinition($factoryId);
            $verdict = $pooling->verdictFor($definition);

            if ($verdict->transportClass === null) {
                if ($verdict->leftSharedBecause !== null) {
                    $container->log($log, sprintf(
                        'Mail transport factory "%s" is left shared, because %s. The transports it '
                        . 'builds are shared with it, so two coroutines sending at once send through '
                        . 'one of them - which for SMTP means one connection carrying both halves of '
                        . 'two dialogues.',
                        $factoryId,
                        $verdict->leftSharedBecause,
                    ));
                }

                continue;
            }

            // No resetter, and pointedly so. What an SMTP transport keeps between sends is a connection
            // it has already paid for - started, greeted, and good for another hundred messages - and
            // handing it back cleared would throw that away on every send. The coroutine that borrows
            // it next wants the connection it left behind, not a blank one; what it must not get is
            // somebody else's half-finished dialogue, which is what having its own is for.
            $definition->addTag(ContainerConstants::TAG_UNMANAGED_FACTORY, [
                'factoryMethod' => self::TRANSPORT_FACTORY_METHOD,
                'returnType' => $verdict->transportClass,
            ]);
        }
    }
}
