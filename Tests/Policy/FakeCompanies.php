<?php

namespace Omnistate\Tests\Policy;

use Omnistate\Model\Company;
use Omnistate\Model\CompanyStatus;
use Omnistate\Registry\CompanyRegistryInterface;

/** A company register answering every SIREN, or failing with $failure; no usage policy declared. */
class FakeCompanies implements CompanyRegistryInterface
{
    /** @var list<string> */
    public array $asked = [];

    public function __construct(public ?\Throwable $failure = null)
    {
    }

    public function name(): string
    {
        return 'fake';
    }

    public function supports(string $identifier): bool
    {
        return 1 === preg_match('/^\d{9}$/', $identifier);
    }

    public function company(string $identifier): Company
    {
        $this->asked[] = $identifier;
        if (null !== $this->failure) {
            throw $this->failure;
        }

        return new Company($identifier, 'DANONE', CompanyStatus::ACTIVE, 'FR', 'fake');
    }

    public function search(string $query, int $limit = 10): array
    {
        $this->asked[] = $query;

        return [];
    }
}
