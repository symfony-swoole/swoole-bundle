<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Controller;

use Symfony\Component\HttpClient\TraceableHttpClient;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Makes one outbound call, then reports what the traced client behind it is holding.
 *
 * The traced client accumulates a trace per call and the profiler empties it again on
 * kernel.response, so what it holds part way through a request is the whole question: its own call
 * and nothing else, or everything every concurrent request has sent so far.
 *
 * Reported rather than asserted here, because an assertion failing inside a coroutine of the server
 * process is not something the test process can see - see the test for how the counts are read.
 */
final readonly class TracedHttpClientController
{
    public const string REPORT_HEADER = 'X-Traced-Requests';

    /**
     * Long enough for every other request in the salvo to have sent its own call before any of them
     * looks at the traces.
     *
     * The wait is what makes the overlap deterministic. Whether the outbound calls themselves overlap
     * depends on the mock web server having workers to answer them with, and on the engine's curl
     * hook; yielding here does not care either way, because sleep() is hooked on both engines and
     * hands the worker to the next request rather than idling it.
     */
    private const int OVERLAP_MICROSECONDS = 300_000;

    public function __construct(
        private HttpClientInterface $httpClient,
        private TraceableHttpClient $tracedHttpClient,
        private string $mockWebServerUrl,
    ) {}

    #[Route(path: '/traced-http-client/{marker}', methods: ['GET'])]
    public function index(string $marker): Response
    {
        if ($this->mockWebServerUrl === '') {
            return new Response('No mock web server configured.', Response::HTTP_SERVICE_UNAVAILABLE);
        }

        // The marker travels in the query so that every request's call is telling apart from every
        // other's in the traces below.
        $url = sprintf('%s?marker=%s', $this->mockWebServerUrl, urlencode($marker));
        // The body has to be read, not just the status: the call is only finished, and the trace only
        // complete, once something asks for the content.
        $this->httpClient->request('GET', $url)->getContent();

        usleep(self::OVERLAP_MICROSECONDS);

        $traced = $this->tracedHttpClient->getTracedRequests();
        $ownTraces = 0;

        foreach ($traced as $trace) {
            $tracedUrl = $trace['url'] ?? '';

            if (!is_string($tracedUrl) || !str_contains($tracedUrl, 'marker=' . $marker)) {
                continue;
            }

            ++$ownTraces;
        }

        // Reported in a header rather than the body: in this environment the profiler appends its
        // whole toolbar to every html response, and a failure quoting the body would be unreadable.
        return new Response('', Response::HTTP_OK, [
            self::REPORT_HEADER => sprintf('marker=%s traces=%d own=%d', $marker, count($traced), $ownTraces),
        ]);
    }
}
