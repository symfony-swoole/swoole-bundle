<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Xdebug;

/**
 * When an http request should attach its worker to the debugging client.
 */
enum RequestAttachMode: string
{
    /**
     * Never. The handler is still in the chain and costs a single enum comparison.
     */
    case Off = 'off';

    /**
     * Only when the request asks, which is what a browser debugging extension is for. The default,
     * because attaching unconditionally puts every request through a connect to an IDE that is usually
     * not listening - bounded by xdebug.connect_timeout_ms, but paid on every asset of every page.
     */
    case Trigger = 'trigger';

    /**
     * Every request, no trigger needed. For driving the server from something that cannot set a cookie
     * or a query parameter, and for the first request after a worker was recycled.
     */
    case Always = 'always';
}
