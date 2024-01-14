<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy;

use ProxyManager\Configuration;
use ProxyManager\Version;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\Generation\ContextualAccessForwarderFactory;

final class Generator extends ContextualAccessForwarderFactory
{
    /**
     * Cached checked class names.
     *
     * @var array<class-string<ContextualProxy<object>&object>>
     */
    private array $checkedClasses = [];

    public function __construct(Configuration $configuration)
    {
        parent::__construct($configuration);
    }

    /**
     * this override method activates the proxy manage class autoloader, which is kind of pain in the ass
     * to activate in Symfony, since Symfony relies directly on Composer and it would be needed to register this
     * autoloader with Composer autoloader initialization.
     *
     * @template RealObjectType of object
     *
     * @param class-string<RealObjectType> $className
     * @param array<string, mixed>         $proxyOptions @codingStandardsIgnoreLine
     *
     * @return class-string<ContextualProxy<RealObjectType>&RealObjectType>
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    protected function generateProxy(string $className, array $proxyOptions = []): string
    {
        if (\array_key_exists($className, $this->checkedClasses)) {
            /** @var class-string<ContextualProxy<RealObjectType>&RealObjectType> $generatedClassName */
            $generatedClassName = $this->checkedClasses[$className];
            \assert(is_a($generatedClassName, $className, true));

            return $generatedClassName;
        }

        $proxyParameters = [
            'className' => $className,
            'factory' => self::class,
            'proxyManagerVersion' => Version::getVersion(),
            'proxyOptions' => $proxyOptions,
        ];
        /** @var class-string<ContextualProxy<RealObjectType>&RealObjectType> $proxyClassName */
        $proxyClassName = $this
            ->configuration
            ->getClassNameInflector()
            ->getProxyClassName($className, $proxyParameters)
        ;

        if (class_exists($proxyClassName)) {
            return $this->checkedClasses[$className] = $proxyClassName;
        }

        $autoloader = $this->configuration->getProxyAutoloader();

        if ($autoloader($proxyClassName)) {
            return $this->checkedClasses[$className] = $proxyClassName;
        }

        /** @var class-string<ContextualProxy<RealObjectType>&RealObjectType> $proxyClassName */
        $proxyClassName = parent::generateProxy($className, $proxyOptions);

        return $this->checkedClasses[$className] = $proxyClassName;
    }
}
