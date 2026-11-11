<?php

namespace Omnistate\Bridge\Symfony\Validator;

use Omnistate\Identifier\Rpps as RppsNumber;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class RppsValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof Rpps) {
            throw new UnexpectedTypeException($constraint, Rpps::class);
        }
        if (null === $value || '' === $value) {
            return;
        }
        $text = \is_scalar($value) || $value instanceof \Stringable ? (string) $value : '';
        if (!RppsNumber::isValid($text)) {
            $this->context->buildViolation($constraint->message)->setParameter('{{ value }}', $this->formatValue($value))->addViolation();
        }
    }
}
