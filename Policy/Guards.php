<?php

namespace Omnistate\Policy;

use Omnisense\Contracts\Policy\DeclaresUsagePolicyInterface;
use Omnisense\Contracts\Policy\InMemoryRateLimiter;
use Omnisense\Contracts\Policy\PolicyGuard;
use Omnisense\Contracts\Policy\RateLimiterInterface;
use Omnisense\Contracts\Policy\Usage;
use Omnisense\Contracts\Policy\UsageLimitReached;
use Omnisense\Contracts\Policy\UsageNotPermitted;
use Omnisense\Contracts\Policy\UsagePolicy;
use Omnistate\Exception\UnavailableException;
use Omnistate\Exception\UsageLimitReachedException;

/**
 * Every register's usage policy, as the site runs it, and its guard: the
 * policy the register declares (DeclaresUsagePolicyInterface, its
 * policy.json) - an unknown one otherwise, which limits nothing -, tightened
 * by the site's ("policies", by register name: UsagePolicy::tighten(), never
 * looser), checked against what the site says it is ("usage": a commercial
 * site on a non-commercial register is refused at once).
 *
 * call() wraps one call to a register: refused before it is made when a
 * limit is reached or while the register's wait lasts
 * (UsageLimitReachedException), counted after. A register's 429
 * (UnavailableException::isTooManyCalls()) holds every call to it for its
 * $retryAfter seconds: the next ones are refused without asking. All count
 * in the limiter given (in memory by default): an application served by
 * several processes gives a shared one.
 */
final class Guards
{
    /** @var array<string, PolicyGuard> */
    private array $guards = [];

    private readonly RateLimiterInterface $limiter;

    private readonly ?Usage $usage;

    /**
     * @param array<string, array<string, mixed>> $policies by register name: the site's tightening, in the policy schema's shape (rateLimits, rights, requirements)
     * @param array<string, mixed>                $usage    what the site is: commercial, open_source, contract
     */
    public function __construct(
        private readonly array $policies = [],
        array $usage = [],
        ?RateLimiterInterface $limiter = null,
    ) {
        $this->usage = [] === $usage ? null : Usage::fromArray($usage);
        $this->limiter = $limiter ?? new InMemoryRateLimiter();
    }

    /**
     * The guard of a register, built on first use.
     *
     * @throws UsageNotPermitted the register's terms do not allow the site's use
     */
    public function guard(string $name, object $registry): PolicyGuard
    {
        if (isset($this->guards[$name])) {
            return $this->guards[$name];
        }
        $declared = $registry instanceof DeclaresUsagePolicyInterface ? $registry->usagePolicy() : UsagePolicy::unknown($name);
        if (UsagePolicy::UNNAMED === $declared->provider || $declared->provider !== $name) {
            $declared = $declared->named($name);
        }
        $effective = $declared->tighten($this->policies[$name] ?? []);
        if (null !== $this->usage && [] !== $violations = $effective->violations($this->usage)) {
            throw UsageNotPermitted::of($name, $violations);
        }

        return $this->guards[$name] = new PolicyGuard($effective, $this->limiter);
    }

    /**
     * One call to a register, under its guard; $operation is the contract
     * method's name ("company", "search", "series"...).
     *
     * @template T
     *
     * @param callable(): T $call
     *
     * @return T
     *
     * @throws UsageLimitReachedException a limit is reached, or the register's wait is not over
     */
    public function call(string $name, object $registry, string $operation, callable $call): mixed
    {
        $guard = $this->guard($name, $registry);
        try {
            return $guard->call($operation, $call);
        } catch (UsageLimitReached $e) {
            throw UsageLimitReachedException::of($e);
        } catch (UnavailableException $e) {
            if ($e->isTooManyCalls() && !$e instanceof UsageLimitReachedException) {
                $guard->backoff()->hold($e->retryAfter ?? 1, $e->getMessage());
            }

            throw $e;
        }
    }

}
