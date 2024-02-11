<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Container;

use ZEngine\Reflection\ReflectionMethod;

/**
 * @phpstan-type ContainerMethodInternals array{
 *   type?: string,
 *   key?: string,
 *   key2?: string
 * }
 */
final class ContainerSourceCodeExtractor
{
    private readonly array $sourceCode;

    public function __construct(string $sourceCode)
    {
        $this->sourceCode = explode(PHP_EOL, $sourceCode);
    }

    /**
     * @return ContainerMethodInternals
     */
    public function getContainerInternalsForMethod(ReflectionMethod $method, bool $isExtension = false): array
    {
        $code = $this->getMethodCode($method);
        $variable = $isExtension ? 'container' : 'this';
        $wasMatched = preg_match(
            '/return \\$' . $variable . '->(?P<type>[a-z]+)\[\'(?P<key>[^\']+)\'\](\[\'(?P<key2>[^\']+)\'\])? \=/',
            $code,
            $matches,
        );

        if (!$wasMatched) {
            return [];
        }

        return $matches;
    }

    public function getMethodCode(ReflectionMethod $method): string
    {
        $startLine = $method->getStartLine() - 1; // it's actually - 1, otherwise you wont get the function() block
        $endLine = $method->getEndLine();
        $length = $endLine - $startLine;

        return implode(PHP_EOL, array_slice($this->sourceCode, $startLine, $length));
    }
}
