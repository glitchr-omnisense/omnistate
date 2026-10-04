<?php

namespace Omnistate\Registry;

use Omnistate\Model\Professional;

/**
 * A register of regulated professionals: France's health professionals
 * (RPPS, through the Annuaire Santé), later its lawyers (the CNB). One
 * contract for every profession: whoever reads it need not know which.
 */
interface ProfessionalRegistryInterface
{
    /** A short name, the Professional's $source: "annuaire-sante". */
    public function name(): string;

    /** Whether it can answer for that identifier (an RPPS number...). */
    public function supports(string $identifier): bool;

    /**
     * The professional, null when the register does not know them.
     *
     * @throws \Omnistate\Exception\UnavailableException the register did not answer
     */
    public function professional(string $identifier): ?Professional;

    /**
     * Professionals by name, those practising in that postcode only when one is given.
     *
     * @return list<Professional>
     *
     * @throws \Omnistate\Exception\UnavailableException
     */
    public function search(string $name, ?string $postcode = null, int $limit = 10): array;
}
