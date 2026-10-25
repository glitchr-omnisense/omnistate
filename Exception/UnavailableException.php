<?php

namespace Omnistate\Exception;

/**
 * The register did not answer: down, overloaded, a member state's service
 * closed for the night (VIES), too many calls (then $retryAfter, in seconds).
 * Not an answer about the company or the number: ask again later. HTTP 401,
 * 403, 429, 5xx, no answer and an unreadable one are all this (the family's
 * rule, docs/providers.md), never null nor an empty list.
 */
class UnavailableException extends OmnistateException
{
    public function __construct(string $message, public readonly ?int $retryAfter = null, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
