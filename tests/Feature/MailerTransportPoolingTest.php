<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use Override;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;

/**
 * That two coroutines sending mail at once send over two connections rather than one.
 *
 * A mail transport is among the most thoroughly stateful services a Symfony application has, and among
 * the least obviously so: an SMTP transport holds an open socket and, on top of it, the state of the
 * conversation being had over that socket - whether the session has been started, how many messages it
 * has carried, and the buffer of what the server said back. For a process sending one mail at a time
 * that is exactly right, which is why it survived this long.
 *
 * SMTP is a dialogue, and every line of it goes over the same socket in order. Two coroutines sharing
 * a transport interleave their halves of it - one's RCPT TO between another's DATA and its body - and
 * what comes of that ranges from a rejected message to one delivered to the wrong recipient. It is not
 * deterministic, so what an application sees is mail that occasionally goes missing.
 *
 * The reading is taken on the stream rather than on the transport, because the stream is the thing that
 * must not be shared - it holds the socket. Two coroutines holding two streams is two connections,
 * which is the fault stated as its own remedy.
 *
 * Nothing is sent and nothing connects: building a transport and asking it for its stream never touches
 * the network, so this needs no mail server.
 *
 * @see \SwooleBundle\SwooleBundle\Bridge\Symfony\Mailer\MailerProcessor
 */
final class MailerTransportPoolingTest extends ServerTestCase
{
    private const string ENVIRONMENT = 'mailer';

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteVarDirectory();
    }

    public function testEachCoroutineIsGivenATransportOfItsOwn(): void
    {
        $report = $this->report();

        self::assertStringContainsString('pooled=yes', $report, sprintf(
            'The transport was left shared, so every coroutine sends through one connection. %s',
            $report,
        ));
        self::assertStringContainsString('smtp=yes', $report, sprintf(
            'The pooled transport is not an SMTP one, so the proxy was generated from the wrong class '
            . 'and nothing below this proves anything. %s',
            $report,
        ));
        self::assertStringContainsString('coroutines=2', $report, sprintf(
            'One of the two coroutines never reported. %s',
            $report,
        ));
        self::assertStringContainsString('distinct_streams=2', $report, sprintf(
            'Two coroutines holding a transport at the same time were given one stream between them, '
            . 'which is one socket carrying both of their dialogues. %s',
            $report,
        ));
    }

    /**
     * The other half of what pooling has to be: an instance per coroutine, kept for as long as that
     * coroutine has work.
     *
     * A pool handing out a fresh transport on every call would satisfy the test above and be useless -
     * an SMTP connection is opened, greeted and then good for a hundred messages, and one built per
     * method call would pay that cost every time and reuse nothing.
     */
    public function testACoroutineKeepsTheTransportItWasGiven(): void
    {
        $report = $this->report();

        self::assertStringContainsString('stable_within_coroutine=yes', $report, sprintf(
            'A coroutine was handed a different transport the second time it asked, so nothing is '
            . 'reusing a connection. %s',
            $report,
        ));
    }

    private function report(): string
    {
        $process = $this->createConsoleProcess(
            ['test:mailer:transport-report'],
            ['APP_ENV' => self::ENVIRONMENT],
        );
        $process->setTimeout(60);
        $process->run();

        $this->assertProcessSucceeded($process);

        return trim($process->getOutput() . $process->getErrorOutput());
    }
}
