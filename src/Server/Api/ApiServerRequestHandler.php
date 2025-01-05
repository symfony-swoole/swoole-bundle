<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Api;

use Exception;
use Swoole\Http\Request;
use Swoole\Http\Response;
use SwooleBundle\SwooleBundle\Client\Http;
use SwooleBundle\SwooleBundle\Server\RequestHandler\RequestHandler;
use Throwable;

final class ApiServerRequestHandler implements RequestHandler
{
    private const SUPPORTED_HTTP_METHODS = [
        Http::METHOD_HEAD,
        Http::METHOD_GET,
        Http::METHOD_POST,
        Http::METHOD_PATCH,
        Http::METHOD_DELETE,
    ];

    /**
     * @var array<string>
     */
    private array $SUPPORTED_HTTP_METHOD_VALUES;

    /**
     * @var array<string, array<string, array{code: int, handler: callable}>>
     */
    private array $routes;

    public function __construct(Api $apiServer)
    {
        $this->SUPPORTED_HTTP_METHOD_VALUES = array_map(
            static fn(Http $http): string => $http->value,
            self::SUPPORTED_HTTP_METHODS
        );
        $this->routes = [
            '/api' => [
                Http::METHOD_GET->value => $this->composeSimpleRouteDefinition(200, $this->getRouteMap(...)),
            ],
            '/api/server' => [
                Http::METHOD_GET->value => $this->composeSimpleRouteDefinition(200, $apiServer->status(...)),
                Http::METHOD_PATCH->value => $this->composeSimpleRouteDefinition(204, $apiServer->reload(...)),
                Http::METHOD_DELETE->value => $this->composeSimpleRouteDefinition(204, $apiServer->shutdown(...)),
            ],
            '/api/server/metrics' => [
                Http::METHOD_GET->value => $this->composeSimpleRouteDefinition(200, $apiServer->metrics(...)),
            ],
            '/healthz' => [
                Http::METHOD_GET->value => $this->composeSimpleRouteDefinition(
                    200,
                    static fn(): array => ['ok' => true]
                ),
            ],
        ];
    }

    /**
     * @throws Exception
     */
    public function handle(Request $request, Response $response): void
    {
        try {
            [$method] = $this->parseRequestInfo($request);
            switch ($method) {
                case Http::METHOD_HEAD->value:
                    $request->server['request_method'] = Http::METHOD_GET->value;
                    $this->sendResponse($response, $this->handleRequest($request)[0]);

                    break;
                case Http::METHOD_GET->value:
                case Http::METHOD_POST->value:
                case Http::METHOD_PATCH->value:
                case Http::METHOD_DELETE->value:
                    [$statusCode, $data] = $this->handleRequest($request);
                    $this->sendResponse($response, $statusCode, $data);

                    return;
                default:
                    $this->sendResponse($response, 405, [
                        'error' => sprintf(
                            'Method "%s" is not supported. Supported ones are: %s.',
                            $method,
                            implode(', ', $this->SUPPORTED_HTTP_METHOD_VALUES)
                        ),
                    ]);

                    return;
            }
        } catch (Throwable $exception) {
            $this->sendErrorExceptionResponse($response, $exception);
        }
    }

    /**
     * @return array{code: int, handler: callable}
     */
    private function composeSimpleRouteDefinition(int $code, callable $handler): array
    {
        return [
            'code' => $code,
            'handler' => $handler,
        ];
    }

    private function sendErrorExceptionResponse(Response $response, Throwable $exception): void
    {
        $this->sendResponse($response, 500, [
            'error' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'line' => $exception->getLine(),
            'file' => $exception->getFile(),
            'trace' => explode("\n", $exception->getTraceAsString()),
        ]);
    }

    /**
     * @return array{string, string}
     */
    private function parseRequestInfo(Request $request): array
    {
        $method = mb_strtoupper((string) $request->server['request_method']);
        $path = mb_strtolower(rtrim((string) $request->server['path_info'], '/'));
        $path = $path === '' ? '/' : $path;

        return [$method, $path];
    }

    /**
     * @return array{int, array{error?: string, routes?: array<mixed>}}
     */
    private function handleRequest(Request $request): array
    {
        [$method, $path] = $this->parseRequestInfo($request);

        if (array_key_exists($path, $this->routes)) {
            $route = $this->routes[$path];
            if (array_key_exists($method, $route)) {
                $action = $route[$method];

                return [$action['code'], $action['handler']($request)];
            }

            return [405, [
                'error' => sprintf(
                    'Method %s for route %s is not valid. Supported ones are: %s.',
                    $method,
                    $path,
                    implode(', ', array_keys($route))
                ),
            ]];
        }

        return [404, [
            'error' => sprintf('Route %s does not exists.', $path),
            'routes' => $this->getRouteMap(),
        ]];
    }

    /**
     * @return array<array<string>>
     */
    private function getRouteMap(): array
    {
        return array_map(static fn(array $route): array => array_keys($route), $this->routes);
    }

    /**
     * @param array<mixed> $data
     */
    private function sendResponse(Response $response, int $statusCode = 200, ?array $data = []): void
    {
        if (empty($data) || $statusCode === 204) {
            $response->status($statusCode === 200 ? 204 : $statusCode);
            $response->end();

            return;
        }

        $response->header(Http::HEADER_CONTENT_TYPE->value, Http::CONTENT_TYPE_APPLICATION_JSON->value);
        $response->status($statusCode);
        $response->end(json_encode($data, JSON_THROW_ON_ERROR));
    }
}
