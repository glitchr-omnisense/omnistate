<?php

namespace Omnistate\Registry;

use Omnistate\Model\Facility;

/**
 * A register of health and social facilities (France: FINESS). A
 * professional registry that knows them too implements both interfaces.
 */
interface FacilityRegistryInterface
{
    public function name(): string;

    /** Whether it can answer for that identifier (a FINESS number...). */
    public function supportsFacility(string $identifier): bool;

    /**
     * @throws \Omnistate\Exception\UnavailableException the register did not answer
     */
    public function facility(string $identifier): ?Facility;
}
