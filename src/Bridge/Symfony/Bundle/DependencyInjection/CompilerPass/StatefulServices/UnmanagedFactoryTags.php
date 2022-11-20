<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices;

use Symfony\Component\DependencyInjection\ContainerBuilder;

final class UnmanagedFactoryTags
{
    /**
     * @var class-string
     */
    private string $serviceClass;

    /**
     * @var array<UnmanagedFactoryTag>
     */
    private array $tags;

    /**
     * @var null|array<array{
     *     factoryMethod: string,
     *     returnType: class-string|string,
     *     limit?: int
     * }>
     */
    private ?array $factoryMethodConfigs = null;

    /**
     * @param class-string $serviceClass
     * @param array<array{
     *     factoryMethod: string,
     *     returnType?: class-string|string,
     *     limit?: int
     * }> $tags
     */
    public function __construct(string $serviceClass, array $tags)
    {
        $this->serviceClass = $serviceClass;
        $this->tags = array_map(fn (array $tag): UnmanagedFactoryTag => new UnmanagedFactoryTag($tag), $tags);
    }

    /**
     * @return array<array{
     *     factoryMethod: string,
     *     returnType: class-string|string,
     *     limit?: int
     * }>
     */
    public function getFactoryMethodConfigs(ContainerBuilder $container): array
    {
        if (null === $this->factoryMethodConfigs) {
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
                        $config['returnType'] = (string) $container->getParameter(trim($config['returnType'], '%'));
                    }

                    return $config;
                },
                $this->tags
            );
        }

        return $this->factoryMethodConfigs;
    }

    /**
     * @param class-string $className
     *
     * @throws \ReflectionException
     */
    private function getReturnTypeForClassMethod(string $className, string $methodName): string
    {
        $refl = new \ReflectionClass($className);
        $reflMethod = $refl->getMethod($methodName);
        $reflReturnType = $reflMethod->getReturnType();

        if (!$reflReturnType instanceof \ReflectionNamedType) {
            throw new \RuntimeException('Only simple return types are supported for now.');
        }

        return $reflReturnType->getName();
    }
}
