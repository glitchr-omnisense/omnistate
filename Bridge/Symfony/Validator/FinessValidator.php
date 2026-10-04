<?php

namespace Omnistate\Bridge\Symfony\Validator;

use Omnistate\Identifier\Finess as FinessNumber;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class FinessValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof Finess) {
            throw new UnexpectedTypeException($constraint, Finess::class);
        }
        if (null === $value || '' === $value) {
            return;
        }
        if (!FinessNumber::isValid((string) $value)) {
            $this->context->buildViolation($constraint->message)->setParameter('{{ value }}', $this->formatValue($value))->addViolation();
        }
    }
}
