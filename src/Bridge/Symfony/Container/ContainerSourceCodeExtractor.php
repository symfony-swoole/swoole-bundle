<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Symfony\Container;

use ZEngine\Reflection\ReflectionMethod;

final class ContainerSourceCodeExtractor
{
    private array $sourceCode;

    public function __construct(string $sourceCode)
    {
        $this->sourceCode = explode(PHP_EOL, $sourceCode);
    }

    /**
     * @return array{}|array{type: string, key: string, key2?: string}
     */
    public function getContainerInternalsForMethod(ReflectionMethod $method, bool $isExtension = false): array
    {
        $code = $this->getMethodCode($method);
        $variable = $isExtension ? 'container' : 'this';

        if (preg_match(
            '/return \\$'.$variable.'->(?P<type>[a-z]+)\[\'(?P<key>[^\']+)\'\](\[\'(?P<key2>[^\']+)\'\])?((\(\))|( \=))/',
            $code,
            $matches
        )) {
            if ($matches['key2'] === '') {
                unset($matches['key2']);
            }

            return $matches;
        }

        if (preg_match(
            '/\\$'.$variable.'->(?P<type>[a-z]+)\[\'(?P<key>[^\']+)\'\] \= \\$instance/',
            $code,
            $matches
        )) {
            return $matches;
        }

        if (preg_match(
            '/\\$'.$variable.'->throw\(/',
            $code,
            $matches
        )) {
            $matches['type'] = 'throw';
            $matches['key'] = 'nevermind';

            return $matches;
        }

        return [];
    }

    public function getMethodCode(ReflectionMethod $method): string
    {
        $startLine = $method->getStartLine() - 1; // it's actually - 1, otherwise you wont get the function() block
        $endLine = $method->getEndLine();
        $length = $endLine - $startLine;

        return implode(PHP_EOL, array_slice($this->sourceCode, $startLine, $length));
    }
}
