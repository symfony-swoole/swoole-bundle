<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Form;

use Override;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * A constraint validator of an application's own, registered as a service: one the validator component finds by
 * its tag and not by its name.
 */
final class OtherConstraintValidator extends ConstraintValidator
{
    #[Override]
    public function validate(mixed $value, Constraint $constraint): void
    {
        // Nothing to validate: only its being a service matters to the tests that use it.
    }
}
