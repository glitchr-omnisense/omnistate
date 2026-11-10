<?php

namespace Omnistate\Exception;

use Omnisense\Contracts\Policy\RateLimit;
use Omnisense\Contracts\Policy\UsageLimitReached;

/**
 * A call Omnistate did not make: a limit of the register's usage policy (as
 * the site tightened it) is reached, or the register asked to wait (a 429
 * and its Retry-After) and the wait is not over. An UnavailableException, as
 * the family's rule says of "ask again later": $retryAfter tells when, $limit
 * which limit, when one did. Its previous is omnisense/contracts'
 * UsageLimitReached.
 */
final class UsageLimitReachedException extends UnavailableException
{
    public function __construct(string $message, ?int $retryAfter = null, public readonly ?string $provider = null, public readonly ?RateLimit $limit = null, ?\Throwable $previous = null)
    {
        parent::__construct($message, $retryAfter, $previous);
    }

    public static function of(UsageLimitReached $reached): self
    {
        return new self($reached->getMessage(), $reached->retryAfter, $reached->provider, $reached->limit, $reached);
    }
}
