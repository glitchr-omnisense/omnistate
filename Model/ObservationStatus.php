<?php

namespace Omnistate\Model;

/**
 * What a source says of one observation's value: final, provisional,
 * estimated, revised... The source's own codes stay in Observation::$flags.
 */
enum ObservationStatus: string
{
    case NORMAL = 'normal';
    /** Not final yet: it may change (INSEE's P, Eurostat's p, SDMX's P). */
    case PROVISIONAL = 'provisional';
    /** INSEE's semi-final value (SD): one more revision is expected. */
    case SEMI_FINAL = 'semi_final';
    case ESTIMATED = 'estimated';
    case FORECAST = 'forecast';
    /** The value was revised since it was first published. */
    case REVISED = 'revised';
    /** A break in the series: not comparable with the value before. */
    case BREAK = 'break';
    case LOW_RELIABILITY = 'low_reliability';
    case NOT_SIGNIFICANT = 'not_significant';
    /** No value: not available, or kept back by statistical confidentiality. */
    case MISSING = 'missing';
    case CONFIDENTIAL = 'confidential';

    /** Whether the value may still change: provisional, semi-final, estimated or forecast. */
    public function isProvisional(): bool
    {
        return \in_array($this, [self::PROVISIONAL, self::SEMI_FINAL, self::ESTIMATED, self::FORECAST], true);
    }
}
