<?php

namespace Omnistate\Tests\Statistics;

use Omnistate\Exception\NotSupportedException;
use Omnistate\Exception\OmnistateException;
use Omnistate\Exception\UsageLimitReachedException;
use Omnistate\Model\IndexChange;
use Omnistate\Model\Observation;
use Omnistate\Model\ObservationStatus;
use Omnistate\Model\Series;
use Omnistate\Model\TimePeriod;
use Omnistate\Omnistate;
use Omnistate\Policy\GuardedStatisticsRegistry;
use Omnistate\Sdmx\Datasets;
use Omnistate\Sdmx\ObservationFlags;
use Omnistate\Sdmx\Structures;
use Omnistate\Exception\UnavailableException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/** The statistics capability: series by ref, datasets, an index's change, the SDMX helpers. */
final class StatisticsTest extends TestCase
{
    public function testASeriesByItsRefOrItsIdAlone(): void
    {
        $fake = new FakeStatistics();
        $other = new FakeStatistics('other');
        $omnistate = new Omnistate(statistics: [$fake, $other]);

        self::assertSame('fake:IDX', $omnistate->series('IDX')?->ref(), 'the first source that reads it');
        self::assertSame('other:IDX', $omnistate->series('other:IDX', last: 2)?->ref());
        self::assertSame([['IDX', null, null, 2]], $other->asked);
        self::assertCount(2, $omnistate->series('other:IDX', last: 2)->observations, 'the same call, non-null as above');

        try {
            $omnistate->series('fake:IRL');
            self::fail('An id the source does not read was asked.');
        } catch (NotSupportedException $e) {
            self::assertSame('The statistics source "fake" does not read "IRL".', $e->getMessage());
        }
        $this->expectException(NotSupportedException::class);
        $omnistate->series('nobody:IDX');
    }

    public function testAnIndexChangeBetweenTwoQuarters(): void
    {
        $fake = new FakeStatistics();
        $omnistate = new Omnistate(statistics: [$fake]);

        $change = $omnistate->indexChange('fake:IDX', TimePeriod::quarter(2026, 1), TimePeriod::quarter(2025, 1));
        self::assertSame([['IDX', '2025-Q1', '2026-Q1', null]], $fake->asked, 'asked over the two, in order');
        self::assertSame(104.06, $change->from->value, 'from is the first period given, whatever the order');
        self::assertSame(100.0, $change->to->value);
        self::assertEqualsWithDelta(100.0 / 104.06, $change->ratio(), 1e-12);
        self::assertSame('fake:IDX', $change->series);
        self::assertSame('Fake, IDX', $change->credit);
        self::assertFalse($change->isProvisional());

        $this->expectException(OmnistateException::class);
        $this->expectExceptionMessage('fake:IDX has no value for 2026-Q3 yet.');
        $omnistate->indexChange('fake:IDX', TimePeriod::quarter(2025, 3), TimePeriod::quarter(2026, 3));
    }

    public function testAProvisionalValueSaysSoAndAMissingOneCannotBeCompared(): void
    {
        $series = new Series('fake', 'IDX', 'An index', observations: [
            new Observation(TimePeriod::quarter(2025, 1), 100.0),
            new Observation(TimePeriod::quarter(2026, 1), 103.0, ObservationStatus::PROVISIONAL),
            new Observation(TimePeriod::quarter(2026, 2), null, ObservationStatus::MISSING),
        ]);

        self::assertTrue(IndexChange::between($series, TimePeriod::quarter(2025, 1), TimePeriod::quarter(2026, 1))->isProvisional());
        $this->expectException(OmnistateException::class);
        IndexChange::between($series, TimePeriod::quarter(2025, 1), TimePeriod::quarter(2026, 2));
    }

    public function testVintagesAndTheCurrentValue(): void
    {
        $june = TimePeriod::month(2026, 6);
        $series = new Series('fake', 'IPC', 'Prices', history: true, observations: [
            new Observation($june, 102.3, vintage: new \DateTimeImmutable('2026-07-10')),
            new Observation($june, 102.12, revised: true, vintage: new \DateTimeImmutable('2026-08-14')),
            new Observation(TimePeriod::month(2026, 7), 102.7, vintage: new \DateTimeImmutable('2026-08-14')),
        ]);

        self::assertSame(102.12, $series->observation('2026-06')?->value, 'the latest vintage');
        self::assertSame([102.3, 102.12], array_map(static fn (Observation $o) => $o->value, $series->vintages($june)));
        self::assertSame('2026-07', (string) $series->latest()?->period);
        self::assertNull($series->observation('2026-08'));
    }

    public function testDatasetsFromEverySourceOrOne(): void
    {
        $omnistate = new Omnistate(statistics: [new FakeStatistics(), new FakeStatistics('other')]);

        self::assertSame(['fake', 'other'], array_map(static fn ($d) => $d->source, $omnistate->datasets('indices')));
        self::assertSame(['other'], array_map(static fn ($d) => $d->source, $omnistate->datasets('indices', 'other')));
        $this->expectException(NotSupportedException::class);
        $omnistate->datasets('indices', 'nobody');
    }

    public function testSourcesAreGivenBehindTheirGuardAndCountedOncePerAnswerKept(): void
    {
        $fake = new FakeStatistics();
        $omnistate = new Omnistate(statistics: [$fake], cache: new ArrayAdapter());

        self::assertInstanceOf(GuardedStatisticsRegistry::class, $omnistate->statisticsRegistry('fake'));
        self::assertSame($fake, $omnistate->statisticsRegistries()[0] instanceof GuardedStatisticsRegistry ? $omnistate->statisticsRegistries()[0]->inner() : null);
        self::assertNull($omnistate->statisticsRegistry('nobody'));
        $omnistate->series('fake:IDX', last: 1);
        $omnistate->series('fake:IDX', last: 1);
        self::assertCount(1, $fake->asked, 'the answer kept');
        self::assertSame(9, $omnistate->guard('fake')->remaining('series'));
        self::assertSame('Source: Fake', $omnistate->policies()['fake']->rights->attribution->text);

        $registry = $omnistate->statisticsRegistry('fake');
        self::assertInstanceOf(GuardedStatisticsRegistry::class, $registry);
        for ($call = 0; $call < 9; ++$call) {
            $registry->series('IDX');
        }
        $this->expectException(UsageLimitReachedException::class);
        $registry->datasets('x');
    }

    public function testObservationFlags(): void
    {
        self::assertSame(ObservationStatus::NORMAL, ObservationFlags::status(['A', 'DEF']));
        self::assertSame(ObservationStatus::PROVISIONAL, ObservationFlags::status(['A', 'P']), 'INSEE\'s OBS_QUAL');
        self::assertSame(ObservationStatus::SEMI_FINAL, ObservationFlags::status(['A', 'SD']));
        self::assertSame(ObservationStatus::PROVISIONAL, ObservationFlags::status(ObservationFlags::letters('bp')), 'Eurostat: provisional before break');
        self::assertSame(['b', 'p'], ObservationFlags::letters('bp'));
        self::assertSame(ObservationStatus::CONFIDENTIAL, ObservationFlags::status(['c']));
        self::assertSame(ObservationStatus::REVISED, ObservationFlags::status(['R']));
        self::assertSame(ObservationStatus::NORMAL, ObservationFlags::status(['d', 'X']), 'definition differs, an unknown code: normal');
    }

    public function testDatasetSearch(): void
    {
        $catalogue = [
            new \Omnistate\Model\Dataset('s', 'IPCH-2025', 'Indices des prix à la consommation harmonisés'),
            new \Omnistate\Model\Dataset('s', 'IRL', 'Indice pour la révision d’un loyer d’habitation'),
            new \Omnistate\Model\Dataset('s', 'IPC-2025', 'Indices des prix à la consommation'),
        ];

        self::assertSame(['IPC-2025', 'IPCH-2025'], array_map(static fn ($d) => $d->id, Datasets::search($catalogue, 'ipc-2025')));
        self::assertSame(['IPCH-2025', 'IPC-2025'], array_map(static fn ($d) => $d->id, Datasets::search($catalogue, 'PRIX consommation')));
        self::assertSame(['IRL'], array_map(static fn ($d) => $d->id, Datasets::search($catalogue, 'revision loyer')), 'accents aside');
        self::assertSame([], Datasets::search($catalogue, 'loyer', 0));
    }

    public function testAStructureMessageIsReadAndAnHtmlPageIsUnavailable(): void
    {
        $xml = '<?xml version="1.0"?><mes:Structure xmlns:mes="http://www.sdmx.org/resources/sdmxml/schemas/v2_1/message" xmlns:str="http://www.sdmx.org/resources/sdmxml/schemas/v2_1/structure" xmlns:com="http://www.sdmx.org/resources/sdmxml/schemas/v2_1/common"><mes:Structures><str:Dataflows>'
            .'<str:Dataflow id="IRL" agencyID="FR1"><com:Annotations><com:Annotation><com:AnnotationURL>https://www.insee.fr/fr/statistiques/series/102758519</com:AnnotationURL></com:Annotation><com:Annotation><com:AnnotationText xml:lang="fr">Nombre de séries : 2</com:AnnotationText></com:Annotation></com:Annotations><com:Name xml:lang="fr">Indice pour la révision d’un loyer d’habitation</com:Name><com:Name xml:lang="en">Index to review a residential rent</com:Name></str:Dataflow>'
            .'<str:Dataflow id="EXR"><com:Name xml:lang="en">Exchange Rates</com:Name></str:Dataflow>'
            .'</str:Dataflows></mes:Structures></mes:Structure>';

        $flows = Structures::dataflows($xml, 's', 'fr', static fn (string $id): string => 'https://example.org/'.$id);
        self::assertSame(['IRL', 'Indice pour la révision d’un loyer d’habitation', 'https://www.insee.fr/fr/statistiques/series/102758519', 2], [$flows[0]->id, $flows[0]->title, $flows[0]->url, $flows[0]->series]);
        self::assertSame(['EXR', 'Exchange Rates', 'https://example.org/EXR', null], [$flows[1]->id, $flows[1]->title, $flows[1]->url, $flows[1]->series]);

        $this->expectException(UnavailableException::class);
        Structures::dataflows('<html><body>Maintenance</body></html>', 's');
    }
}
