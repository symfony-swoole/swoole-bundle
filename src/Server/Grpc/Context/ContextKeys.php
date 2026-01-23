<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Grpc\Context;

/**
 * Class ContextKeys
 *
 * Defines constant keys used for storing and retrieving context-related data.
 */
final class ContextKeys
{
    /**
     * Key for the service method definition in the context.
     */
    public const SERVICE_METHOD_DEFINITION = 'service-method-definition';

    /**
     * Key for the service name.
     */
    public const SERVICE_NAME = 'service-name';

    /**
     * Key for the method name.
     */
    public const METHOD_NAME = 'method-name';

    /**
     * Key for cache hit indicator.
     */
    public const CACHE_HIT = 'cache-hit';

    /**
     * Key for request start time.
     */
    public const REQUEST_START_TIME = 'request-start-time';

    /**
     * Key for custom metadata.
     */
    public const METADATA = 'metadata';

    /**
     * Key for Symfony kernel instance.
     */
    public const SYMFONY_KERNEL = 'symfony.kernel';

    /**
     * Key for Symfony HttpFoundation request.
     */
    public const SYMFONY_HTTP_REQUEST = 'symfony.http_request';

    /**
     * Key for Symfony HttpFoundation response.
     */
    public const SYMFONY_HTTP_RESPONSE = 'symfony.http_response';
}
