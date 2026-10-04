<?php

namespace Omnistate\Bridge\Symfony\Validator;

use Symfony\Component\Validator\Constraint;

/** A French health professional's RPPS number: eleven digits, its Luhn key right (its IDNPS form, 8 + RPPS, too). */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class Rpps extends Constraint
{
    public string $message = 'This is not a valid RPPS number.';

    public function __construct(?string $message = null, ?array $groups = null, mixed $payload = null)
    {
        parent::__construct(null, $groups, $payload);
        $this->message = $message ?? $this->message;
    }
}
