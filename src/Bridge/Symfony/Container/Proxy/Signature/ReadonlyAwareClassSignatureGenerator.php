<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\Signature;

use Laminas\Code\Generator\ClassGenerator;
use Laminas\Code\Generator\PropertyGenerator;
use Laminas\Code\Generator\PropertyValueGenerator;
use Override;
use ProxyManager\Signature\ClassSignatureGenerator;
use ProxyManager\Signature\ClassSignatureGeneratorInterface;
use ProxyManager\Signature\SignatureGeneratorInterface;

/**
 * Signs a proxy in a place a readonly proxy can hold the signature.
 *
 * ProxyManager signs every proxy it generates with a private static property holding a hash of the
 * parameters it was generated from, and reads that property back to decide whether a proxy found on
 * disk is still the one it would generate. A readonly class can have neither: no static property, and
 * no property with a default value. So a readonly proxy - the only kind that may extend a readonly
 * class - carries its signature as a private class constant of the same name instead.
 *
 * Every other proxy is signed by ProxyManager's own generator, which this delegates to. The
 * configuration it is installed on is shared with ProxyManager's own proxy factories, and nothing they
 * generate may change because of it.
 *
 * @see ReadonlyAwareSignatureChecker for the reading side
 */
final readonly class ReadonlyAwareClassSignatureGenerator implements ClassSignatureGeneratorInterface
{
    private ClassSignatureGeneratorInterface $propertySignatures;

    public function __construct(private SignatureGeneratorInterface $signatureGenerator)
    {
        $this->propertySignatures = new ClassSignatureGenerator($signatureGenerator);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    #[Override]
    public function addSignature(ClassGenerator $classGenerator, array $parameters): ClassGenerator
    {
        if (!$classGenerator->isReadonly()) {
            return $this->propertySignatures->addSignature($classGenerator, $parameters);
        }

        $classGenerator->addConstantFromGenerator(new PropertyGenerator(
            self::constantName($this->signatureGenerator, $parameters),
            new PropertyValueGenerator($this->signatureGenerator->generateSignature($parameters)),
            PropertyGenerator::FLAG_CONSTANT | PropertyGenerator::FLAG_PRIVATE,
        ));

        return $classGenerator;
    }

    /**
     * The name ProxyManager gives the static property, so the two forms differ only in what they are.
     *
     * @param array<string, mixed> $parameters
     */
    public static function constantName(SignatureGeneratorInterface $signatureGenerator, array $parameters): string
    {
        return 'signature' . $signatureGenerator->generateSignatureKey($parameters);
    }
}
