<?php

namespace Omnistate\Bridge\Symfony;

use Omnistate\Exception\OmnistateException;
use Omnistate\Identifier\Siren;
use Omnistate\Model\Company;
use Omnistate\Omnistate;

/**
 * Companies found by name (or number), as the search field and its endpoint
 * hand them out: one flat row each, with what a form may fill - the SIREN, the
 * head office's SIRET and address, and the French intra-EU VAT number derived
 * from the SIREN (VIES still has the last word on it).
 */
final class CompanySearch
{
    public const MIN_LENGTH = 3;
    public const MAX_RESULTS = 25;

    public function __construct(private readonly Omnistate $omnistate)
    {
    }

    /**
     * @return list<array{siren: string, siret: ?string, name: string, vatNumber: ?string, address: ?string, postalCode: ?string, city: ?string, activity: ?string, legalForm: ?string, active: bool, country: string}>
     *
     * @throws OmnistateException when no registry answers
     */
    public function search(string $query, int $limit = 10): array
    {
        $query = trim($query);
        if (mb_strlen($query) < self::MIN_LENGTH) {
            return [];
        }

        return array_values(array_map(self::row(...), $this->omnistate->search($query, max(1, min(self::MAX_RESULTS, $limit)))));
    }

    public static function row(Company $company): array
    {
        $address = $company->headOffice?->address;

        return [
            'siren' => $company->identifier,
            'siret' => $company->headOffice?->identifier,
            'name' => $company->legalName ?? $company->name,
            'vatNumber' => 'FR' === $company->country && Siren::isValid($company->identifier) ? Siren::toVatNumber($company->identifier) : null,
            'address' => null !== $address ? (string) $address : null,
            'postalCode' => $address?->postalCode,
            'city' => $address?->city,
            'activity' => null !== $company->activity ? (string) $company->activity : null,
            'legalForm' => null !== $company->legalForm ? (string) $company->legalForm : null,
            'active' => $company->isActive(),
            'country' => $company->country,
        ];
    }
}
