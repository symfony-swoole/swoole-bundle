<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\Mime;

use Egulias\EmailValidator\EmailValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Mime\MimeAddressValidatorInstaller;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Exception\RfcComplianceException;

/**
 * What the installer itself does, which is put the validator it was given where Address looks.
 *
 * That the validator it is given is a pooled one is the extension's business - see
 * MimeAddressValidatorRegistrationTest for the tag that arranges it, and
 * MailerTransportPoolingTest's sibling MimeAddressValidatorInstalledTest for a worker actually handing
 * two coroutines two of them.
 */
#[CoversClass(MimeAddressValidatorInstaller::class)]
final class MimeAddressValidatorInstallerTest extends TestCase
{
    private const string VALIDATOR_PROPERTY = 'validator';

    public function testItPutsTheValidatorItWasGivenBehindEveryAddress(): void
    {
        $validator = new EmailValidator();

        (new MimeAddressValidatorInstaller($validator))->boot();

        self::assertSame($validator, self::installedValidator());
    }

    /**
     * The case that matters: anything constructing an Address before the server boots leaves symfony's
     * own validator in the static, and this has to replace it rather than only fill an empty one.
     */
    public function testItReplacesAValidatorSymfonyMimeBuiltForItself(): void
    {
        (new ReflectionProperty(Address::class, self::VALIDATOR_PROPERTY))
            ->setValue(null, new EmailValidator());
        $validator = new EmailValidator();

        (new MimeAddressValidatorInstaller($validator))->boot();

        self::assertSame($validator, self::installedValidator());
    }

    /**
     * Writing a static is itself a cross-coroutine write, so a second boot with the same validator has
     * to leave it alone rather than write it again under whatever is already validating through it.
     */
    public function testItDoesNotWriteTheStaticAgainForAValidatorAlreadyInPlace(): void
    {
        $installer = new MimeAddressValidatorInstaller(new EmailValidator());
        $installer->boot();
        $installed = self::installedValidator();

        $installer->boot();

        self::assertSame($installed, self::installedValidator());
    }

    /**
     * A second server in one process, with a pool of its own: that one does have to go in, or every
     * Address built afterwards validates through a pool nothing releases instances back to.
     */
    public function testItInstallsADifferentValidatorOverAnEarlierOne(): void
    {
        (new MimeAddressValidatorInstaller(new EmailValidator()))->boot();
        $replacement = new EmailValidator();

        (new MimeAddressValidatorInstaller($replacement))->boot();

        self::assertSame($replacement, self::installedValidator());
    }

    /**
     * The point of all of it: Address still validates, and still validates correctly.
     */
    public function testItLeavesAddressValidatingAsBefore(): void
    {
        (new MimeAddressValidatorInstaller(new EmailValidator()))->boot();

        self::assertSame('noreply@example.com', (new Address('noreply@example.com'))->getAddress());

        $this->expectException(RfcComplianceException::class);

        new Address('not-an-address');
    }

    private static function installedValidator(): EmailValidator
    {
        $installed = (new ReflectionProperty(Address::class, self::VALIDATOR_PROPERTY))->getValue();
        self::assertInstanceOf(EmailValidator::class, $installed);

        return $installed;
    }
}
