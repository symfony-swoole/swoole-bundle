<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Xdebug;

use Swoole\Http\Request as SwooleRequest;
use Swoole\Http\Response as SwooleResponse;
use SwooleBundle\SwooleBundle\Server\RequestHandler\RequestHandler;

/**
 * Attaches the worker serving this request to the debugging client, when the request asks for it.
 *
 * Sits at the top of the request handler chain, outside every other handler including the one that
 * establishes the coroutine context. A session opened here is open for everything that follows without
 * exception, so a breakpoint can be put anywhere a request reaches - the application's code, and this
 * bundle's own handlers along the way.
 *
 * The trigger is the request's own, and is read the way xdebug documents it rather than the way this
 * bundle would prefer: XDEBUG_SESSION as a cookie is what browser extensions set and what PhpStorm's
 * bookmarklets set, XDEBUG_TRIGGER is the general form and is accepted as a cookie or a query
 * parameter so that a single request can be driven from curl. Neither value is inspected - xdebug's
 * own trigger_value filtering is not reimplemented here, because the decision this class makes is
 * only whether to offer a session at all.
 *
 * @see XdebugClient for why this exists rather than xdebug.start_with_request
 */
final readonly class AttachXdebugRequestHandler implements RequestHandler
{
    private const string COOKIE_SESSION = 'XDEBUG_SESSION';

    private const string TRIGGER = 'XDEBUG_TRIGGER';

    public function __construct(
        private RequestHandler $decorated,
        private XdebugClient $xdebug,
        private RequestAttachMode $mode = RequestAttachMode::Trigger,
    ) {}

    public function handle(SwooleRequest $request, SwooleResponse $response): void
    {
        if ($this->shouldAttach($request)) {
            $this->xdebug->attach();
        }

        $this->decorated->handle($request, $response);
    }

    private function shouldAttach(SwooleRequest $request): bool
    {
        return match ($this->mode) {
            RequestAttachMode::Off => false,
            RequestAttachMode::Always => true,
            RequestAttachMode::Trigger => $this->requestAsks($request),
        };
    }

    private function requestAsks(SwooleRequest $request): bool
    {
        /** @var array<string, string> $cookies */
        $cookies = $request->cookie ?? [];
        /** @var array<string, string> $query */
        $query = $request->get ?? [];

        return isset($cookies[self::COOKIE_SESSION])
            || isset($cookies[self::TRIGGER])
            || isset($query[self::COOKIE_SESSION])
            || isset($query[self::TRIGGER]);
    }
}
