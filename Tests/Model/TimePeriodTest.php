<?php

namespace Omnistate\Tests\Model;

use Omnistate\Exception\OmnistateException;
use Omnistate\Model\Frequency;
use Omnistate\Model\TimePeriod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TimePeriodTest extends TestCase
{
    /** @return iterable<string, array{string, string, Frequency, string, string}> */
    public static function periods(): iterable
    {
        yield 'a year' => ['2026', '2026', Frequency::ANNUAL, '2026-01-01', '2026-12-31'];
        yield 'a half-year' => ['2026-S2', '2026-S2', Frequency::SEMIANNUAL, '2026-07-01', '2026-12-31'];
        yield 'a quarter' => ['2026-Q2', '2026-Q2', Frequency::QUARTERLY, '2026-04-01', '2026-06-30'];
        yield 'a quarter, INSEE\'s French way' => ['2026-T3', '2026-Q3', Frequency::QUARTERLY, '2026-07-01', '2026-09-30'];
        yield 'a quarter, run together' => ['2026Q4', '2026-Q4', Frequency::QUARTERLY, '2026-10-01', '2026-12-31'];
        yield 'a month' => ['2024-02', '2024-02', Frequency::MONTHLY, '2024-02-01', '2024-02-29'];
        yield 'a month, SDMX\'s M' => ['2026M09', '2026-09', Frequency::MONTHLY, '2026-09-01', '2026-09-30'];
        yield 'an ISO week' => ['2026-W01', '2026-W01', Frequency::WEEKLY, '2025-12-29', '2026-01-04'];
        yield 'a day' => ['2026-11-09', '2026-11-09', Frequency::DAILY, '2026-11-09', '2026-11-09'];
    }

    #[DataProvider('periods')]
    public function testPeriodsAsSourcesWriteThem(string $written, string $sdmx, Frequency $frequency, string $start, string $end): void
    {
        $period = TimePeriod::parse($written);

        self::assertSame($sdmx, (string) $period);
        self::assertSame($frequency, $period->frequency);
        self::assertSame($start, $period->start()->format('Y-m-d'));
        self::assertSame($end, $period->end()->format('Y-m-d'));
        self::assertTrue($period->equals(TimePeriod::parse($sdmx, $frequency)));
    }

    public function testPeriodsCountInTheirUnit(): void
    {
        self::assertSame('2025-Q2', (string) TimePeriod::quarter(2026, 2)->shift(-4), 'a year before');
        self::assertSame('2027-Q1', (string) TimePeriod::quarter(2026, 4)->next());
        self::assertSame('2025-12', (string) TimePeriod::month(2026, 1)->previous());
        self::assertSame('2027-S1', (string) TimePeriod::semester(2026, 2)->next());
        self::assertSame('2026-W53', (string) TimePeriod::week(2027, 1)->previous(), '2026 has 53 ISO weeks');
        self::assertSame('2026-03-01', (string) TimePeriod::day('2026-02-28')->next());
        self::assertSame('2028', (string) TimePeriod::year(2026)->shift(2));
        self::assertSame(Frequency::BUSINESS, TimePeriod::parse('2026-11-09', Frequency::BUSINESS)->next()->frequency);
    }

    public function testComparisonAndTheQuarterOfADate(): void
    {
        self::assertSame(-1, TimePeriod::quarter(2025, 4)->compare(TimePeriod::quarter(2026, 1)));
        self::assertSame(0, TimePeriod::quarter(2026, 1)->compare(TimePeriod::parse('2026-T1')));
        self::assertSame(1, TimePeriod::year(2026)->compare(TimePeriod::quarter(2026, 1)), 'the same start, a later end');
        self::assertSame('2026-Q2', (string) TimePeriod::quarterOf(new \DateTimeImmutable('2026-05-17 23:30', new \DateTimeZone('Europe/Paris'))));
        self::assertTrue(TimePeriod::quarter(2026, 2)->contains(new \DateTimeImmutable('2026-06-30')));
        self::assertFalse(TimePeriod::quarter(2026, 2)->contains(new \DateTimeImmutable('2026-07-01')));
    }

    /** @return iterable<string, array{string}> */
    public static function notPeriods(): iterable
    {
        yield 'a fifth quarter' => ['2026-Q5'];
        yield 'a thirteenth month' => ['2026-13'];
        yield 'a 30th of February' => ['2026-02-30'];
        yield 'a week 53 of a year without' => ['2025-W53'];
        yield 'words' => ['last quarter'];
    }

    #[DataProvider('notPeriods')]
    public function testWhatIsNoPeriodIsSaid(string $written): void
    {
        $this->expectException(OmnistateException::class);
        TimePeriod::parse($written);
    }
}
