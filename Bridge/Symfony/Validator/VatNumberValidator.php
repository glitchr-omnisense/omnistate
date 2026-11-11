<?php

namespace Omnistate\Bridge\Symfony\Validator;

use Omnistate\Exception\OmnistateException;
use Omnistate\Identifier\VatNumber as VatNumberRules;
use Omnistate\Omnistate;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class VatNumberValidator extends ConstraintValidator
{
    public function __construct(private readonly ?Omnistate $omnistate = null)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof VatNumber) {
            throw new UnexpectedTypeException($constraint, VatNumber::class);
        }
        if (null === $value || '' === $value) {
            return;
        }
        $text = \is_scalar($value) || $value instanceof \Stringable ? (string) $value : '';
        if (!VatNumberRules::isWellFormed($text)) {
            $this->context->buildViolation($constraint->message)->setParameter('{{ value }}', $this->formatValue($value))->addViolation();

            return;
        }
        if (!$constraint->checkExistence || null === $this->omnistate) {
            return;
        }
        try {
            $known = $this->omnistate->vat($text)->valid;
        } catch (OmnistateException) {
            return; // the register did not answer: not a reason to refuse the number
        }
        if (!$known) {
            $this->context->buildViolation($constraint->unknownMessage)->setParameter('{{ value }}', $this->formatValue($value))->addViolation();
        }
    }
}
