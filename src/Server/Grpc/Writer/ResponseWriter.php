<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Grpc\Writer;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SwooleBundle\SwooleBundle\Server\Grpc\Constant;
use SwooleBundle\SwooleBundle\Server\Grpc\Context\Context;

/**
 * Writes gRPC responses to Swoole HTTP responses.
 *
 * Handles headers, trailers, and payload formatting according to gRPC spec.
 */
final readonly class ResponseWriter
{
    public function __construct(
        private LoggerInterface $logger = new NullLogger(),
    ) {
    }

    /**
     * Write the gRPC response to the Swoole response.
     */
    public function write(Context $context): void
    {
        $rawResponse = $context->getResponse()->getSwooleResponse();
        $grpcResponse = $context->getResponse();

        // Prepare headers
        $headers = [
            'content-type' => $context->getRequest()->getContentType(),
            'trailer' => 'grpc-status, grpc-message',
        ];

        // Prepare trailers
        $trailers = [
            Constant::GRPC_STATUS => $grpcResponse->getStatus(),
            Constant::GRPC_MESSAGE => $grpcResponse->getMessage(),
        ];

        // Format payload with gRPC framing
        $payload = $this->framePayload($grpcResponse->getPayload());

        try {
            // Set headers
            foreach ($headers as $name => $value) {
                $rawResponse->header($name, $value);
            }

            // Set trailers (also as headers for HTTP/2 compatibility)
            foreach ($trailers as $name => $value) {
                $rawResponse->trailer($name, (string) $value);
                $rawResponse->header($name, (string) $value);
            }

            // Send response
            $rawResponse->end($payload);
        } catch (\Swoole\Exception $e) {
            $this->logger->warning(
                'Failed to send gRPC response: ' . $e->getMessage(),
                [
                    'error_code' => $e->getCode(),
                    'trace' => $e->getTraceAsString(),
                ]
            );
        }
    }

    /**
     * Frame the payload according to gRPC specification.
     *
     * Format: [1-byte compression flag][4-byte message length][message]
     */
    private function framePayload(string $payload): string
    {
        if ($payload === '') {
            return '';
        }

        // Compression flag: 0 = not compressed
        // Message length: 4 bytes, network byte order (big-endian)
        return pack('CN', 0, strlen($payload)) . $payload;
    }
}
