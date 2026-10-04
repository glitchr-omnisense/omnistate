<?php

namespace Omnistate\Bridge\Symfony\Validator;

use Symfony\Component\Validator\Constraint;

/** A French health or social facility's FINESS number: nine characters, its Luhn key right. */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class Finess extends Constraint
{
    public string $message = 'This is not a valid FINESS number.';

    public function __construct(?string $message = null, ?array $groups = null, mixed $payload = null)
    {
        parent::__construct(null, $groups, $payload);
        $this->message = $message ?? $this->message;
    }
}
