<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\Signature;

use Override;
use ProxyManager\Signature\Exception\InvalidSignatureException;
use ProxyManager\Signature\Exception\MissingSignatureException;
use ProxyManager\Signature\SignatureChecker;
use ProxyManager\Signature\SignatureCheckerInterface;
use ProxyManager\Signature\SignatureGeneratorInterface;
use ReflectionClass;

/**
 * Reads back the signature {@see ReadonlyAwareClassSignatureGenerator} wrote.
 *
 * A readonly proxy is checked against its signature constant; every other class is handed to
 * ProxyManager's own checker, which looks for the static property. The two never meet: a readonly class
 * cannot have that property, and a proxy of any other kind is never signed with a constant.
 */
final readonly class ReadonlyAwareSignatureChecker implements SignatureCheckerInterface
{
    private SignatureCheckerInterface $propertySignatures;

    public function __construct(private SignatureGeneratorInterface $signatureGenerator)
    {
        $this->propertySignatures = new SignatureChecker($signatureGenerator);
    }

    /**
     * @param ReflectionClass<object> $class
     * @param array<string, mixed> $parameters
     * @throws InvalidSignatureException
     * @throws MissingSignatureException
     */
    #[Override]
    public function checkSignature(ReflectionClass $class, array $parameters): void
    {
        if (!$class->isReadOnly()) {
            $this->propertySignatures->checkSignature($class, $parameters);

            return;
        }

        $expected = $this->signatureGenerator->generateSignature($parameters);
        $constant = $class->getReflectionConstant(
            ReadonlyAwareClassSignatureGenerator::constantName($this->signatureGenerator, $parameters),
        );
        $actual = $constant === false ? null : $constant->getValue();

        if (!is_string($actual)) {
            throw MissingSignatureException::fromMissingSignature($class, $parameters, $expected);
        }

        if ($actual !== $expected) {
            throw InvalidSignatureException::fromInvalidSignature($class, $parameters, $actual, $expected);
        }
    }
}
