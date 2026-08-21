<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Form;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidatorFactoryInterface;
use Symfony\Component\Validator\ConstraintValidatorInterface;

/**
 * A validator factory that is not Symfony's own, standing in for an application that builds its
 * constraint validators some other way. It has the memo the real one has, which is what makes it
 * something to pool.
 */
final class OtherConstraintValidatorFactory implements ConstraintValidatorFactoryInterface
{
    /**
     * @var array<string, ConstraintValidatorInterface>
     */
    private array $validators = [];

    public function getInstance(Constraint $constraint): ConstraintValidatorInterface
    {
        $name = $constraint->validatedBy();

        return $this->validators[$name] ??= new $name();
    }
}
