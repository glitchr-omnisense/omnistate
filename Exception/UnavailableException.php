<?php

namespace Omnistate\Exception;

/**
 * The register did not answer: down, overloaded, a member state's service
 * closed for the night (VIES), too many calls (then $retryAfter, in seconds).
 * Not an answer about the company or the number: ask again later. HTTP 401,
 * 403, 429, 5xx, no answer and an unreadable one are all this (the family's
 * rule, docs/providers.md), never null nor an empty list.
 *
 * $status is the HTTP status the register answered, when it answered one: a
 * 429 is "too many calls", and Omnistate then holds every call to that
 * register for $retryAfter seconds (its usage policy's guard).
 */
class UnavailableException extends OmnistateException
{
    public function __construct(string $message, public readonly ?int $retryAfter = null, ?\Throwable $previous = null, public readonly ?int $status = null)
    {
        parent::__construct($message, 0, $previous);
    }

    /** The register answered 429 Too Many Requests. */
    public function isTooManyCalls(): bool
    {
        return 429 === $this->status;
    }
}
