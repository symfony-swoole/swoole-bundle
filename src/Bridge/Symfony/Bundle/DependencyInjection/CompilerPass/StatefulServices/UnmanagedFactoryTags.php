<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices;

use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use RuntimeException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use UnexpectedValueException;

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
     * @var null|array<string>
     */
    private ?array $factoryMethods = null;

    /**
     * @param class-string                                                          $serviceClass
     * @param array<array{factoryMethod: string, returnType?: class-string|string}> $tags
     */
    public function __construct(string $serviceClass, array $tags)
    {
        $this->serviceClass = $serviceClass;
        $this->tags = array_map(fn (array $tag): UnmanagedFactoryTag => new UnmanagedFactoryTag($tag), $tags);
    }

    /**
     * @return array<string>
     */
    public function getFactoryMethods(): array
    {
        if (null === $this->factoryMethods) {
            $this->factoryMethods = array_map(
                fn (UnmanagedFactoryTag $tag): string => $tag->getFactoryMethod(),
                $this->tags
            );
        }

        return $this->factoryMethods;
    }

    /**
     * @throws ReflectionException
     *
     * @return class-string
     */
    public function getFactoryReturnType(ContainerBuilder $container): string
    {
        $returnType = $this->tags[0]->getReturnType() ??
            $this->getReturnTypeForClassMethod($this->serviceClass, $this->getFactoryMethods()[0]);

        if (str_starts_with($returnType, '%')) {
            $returnType = (string) $container->getParameter(trim($returnType, '%'));
        }

        if (!class_exists($returnType)) {
            throw new UnexpectedValueException(sprintf('Class does not exist: %s', $returnType));
        }

        return $returnType;
    }

    /**
     * @param class-string $className
     *
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
