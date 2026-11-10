<?php

namespace Omnistate\Tests\Policy;

use Omnisense\Contracts\Policy\DeclaresUsagePolicyInterface;
use Omnisense\Contracts\Policy\UsagePolicy;

/** The same register, declaring the policy given. */
final class FakeDeclaredCompanies extends FakeCompanies implements DeclaresUsagePolicyInterface
{
    public function __construct(private readonly UsagePolicy $policy, ?\Throwable $failure = null)
    {
        parent::__construct($failure);
    }

    public function usagePolicy(): UsagePolicy
    {
        return $this->policy;
    }
}
