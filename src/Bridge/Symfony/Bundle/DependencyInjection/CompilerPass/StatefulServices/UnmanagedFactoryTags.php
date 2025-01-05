<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices;

use Assert\Assertion;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use RuntimeException;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class UnmanagedFactoryTags
{
    /**
     * @var array<UnmanagedFactoryTag>
     */
    private readonly array $tags;

    /**
     * @var array<array{factoryMethod: string, returnType: class-string, limit?: int, resetter?: string}>|null
     */
    private ?array $factoryMethodConfigs = null;

    /**
     * @param class-string $serviceClass
     * @param array<array{
     *     factoryMethod: string,
     *     returnType?: class-string|string,
     *     limit?: int,
     *     resetter?: string
     * }> $tags
     */
    public function __construct(
        private readonly string $serviceClass,
        array $tags,
    ) {
        $this->tags = array_map(static fn(array $tag): UnmanagedFactoryTag => new UnmanagedFactoryTag($tag), $tags);
    }

    /**
     * @return array<array{
     *     factoryMethod: string,
     *     returnType: class-string,
     *     limit?: int,
     *     resetter?: string
     * }>
     */
    public function getFactoryMethodConfigs(ContainerBuilder $container): array
    {
        if ($this->factoryMethodConfigs === null) {
            $this->factoryMethodConfigs = array_map(
                function (UnmanagedFactoryTag $tag) use ($container): array {
                    $config = $tag->toArray();

                    if (!isset($config['returnType'])) {
                        $config['returnType'] = $this->getReturnTypeForClassMethod(
                            $this->serviceClass,
                            $config['factoryMethod']
                        );
                    }

                    if (str_starts_with($config['returnType'], '%')) {
                        $returnType = $container->getParameter(trim($config['returnType'], '%'));
                        $config['returnType'] = $returnType;
                    }

                    Assertion::classExists($config['returnType']);

                    return $config;
                },
                $this->tags
            );
        }

        return $this->factoryMethodConfigs;
    }

    /**
     * @param class-string $className
     * @throws ReflectionException
     */
    private function getReturnTypeForClassMethod(string $className, string $methodName): string
    {
        $refl = new ReflectionClass($className);
        $reflMethod = $refl->getMethod($methodName);
        $reflReturnType = $reflMethod->getReturnType();

        if (!$reflReturnType instanceof ReflectionNamedType) {
            throw new RuntimeException('Only simple return types are supported for now.');
        }

        return $reflReturnType->getName();
    }
}
