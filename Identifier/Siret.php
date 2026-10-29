<?php

namespace Omnistate\Identifier;

/**
 * A French establishment's SIRET: its company's SIREN and a five-digit NIC,
 * fourteen digits under a Luhn check - La Poste's (SIREN 356 000 000) also
 * when the sum of their digits is a multiple of 5, the rule its many
 * establishments' numbers follow (its head office, 356 000 000 00048, passes
 * Luhn and not the sum).
 */
final class Siret
{
    private const LA_POSTE = '356000000';

    /** "901 821 074 00019" -> "90182107400019"; null when it is not fourteen digits. */
    public static function normalize(?string $siret): ?string
    {
        $siret = preg_replace('/[\s.\-]/', '', (string) $siret);

        return preg_match('/^\d{14}$/', (string) $siret) ? $siret : null;
    }

    public static function isValid(?string $siret): bool
    {
        $siret = self::normalize($siret);
        if (null === $siret || !Siren::isValid(substr($siret, 0, 9))) {
            return false;
        }
        if (Luhn::isValid($siret)) {
            return true;
        }

        return self::LA_POSTE === substr($siret, 0, 9) && 0 === array_sum(array_map('intval', str_split($siret))) % 5;
    }

    public static function siren(string $siret): string
    {
        return substr(self::normalize($siret) ?? throw new \InvalidArgumentException(sprintf('"%s" is not a SIRET.', $siret)), 0, 9);
    }
}
