<?php

namespace Omnistate\Registry;

use Omnistate\Model\VatCheck;

/** A register of VAT numbers: the EU's VIES... */
interface VatRegistryInterface
{
    public function name(): string;

    /** Whether it answers for that (normalized) number's country. */
    public function supports(string $number): bool;

    /**
     * @param string|null $requester your own VAT number: the register may then
     *                               give a consultation number, the proof of the check
     *
     * @throws \Omnistate\Exception\UnavailableException the register (or the member state behind it) did not answer
     * @throws \Omnistate\Exception\OmnistateException    the register refused the check itself (VIES: a requester it does not accept)
     */
    public function check(string $number, ?string $requester = null): VatCheck;
}
