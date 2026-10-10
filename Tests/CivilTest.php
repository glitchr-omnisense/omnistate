<?php

namespace Omnistate\Tests;

use Omnistate\Bridge\Symfony\OmnistateBundle;
use Omnistate\Exception\NotSupportedException;
use Omnistate\Exception\UnavailableException;
use Omnistate\Identifier\CountryCode;
use Omnistate\MatchId\MatchId;
use Omnistate\Model\Archive;
use Omnistate\Model\CivilPerson;
use Omnistate\Model\CivilQuery;
use Omnistate\Model\CivilRecord;
use Omnistate\Model\CivilRecordKind;
use Omnistate\Model\PartialDate;
use Omnistate\Model\Period;
use Omnistate\Model\Place;
use Omnistate\Model\Sex;
use Omnistate\Nara\Nara;
use Omnistate\NationalArchivesUk\NationalArchivesUk;
use Omnistate\Omnistate;
use Omnistate\OpenArchieven\OpenArchieven;
use Omnistate\Registry\CivilRegistryInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class CivilTest extends TestCase
{
    public function testADateKnowsOnlyWhatTheRegisterKnows(): void
    {
        self::assertSame('1932-11-29', (string) PartialDate::parse('19321129'));
        self::assertSame('1932-11-29', (string) PartialDate::parse('29/11/1932'));
        self::assertSame('1932-11-29', (string) PartialDate::parse('1932-11-29'));
        self::assertSame('1932-11', (string) PartialDate::parse('19321100'), 'an unknown day is not a 1st');
        self::assertSame('1932', (string) PartialDate::parse('19320000'));
        self::assertSame('1932', (string) PartialDate::parse('1932'));
        self::assertSame('1932-11', (string) PartialDate::parse('11/1932'));
        self::assertNull(PartialDate::parse('00000000'));
        self::assertNull(PartialDate::parse('vers 1932'));
        self::assertNull(PartialDate::parse(null));

        $year = new PartialDate(1932);
        self::assertFalse($year->isComplete());
        self::assertNull($year->toDate());
        self::assertSame('1932-01-01', $year->earliest()->format('Y-m-d'));
        self::assertSame('1932-12-31', $year->latest()->format('Y-m-d'));
        self::assertSame('1932-02-29', (new PartialDate(1932, 2))->latest()->format('Y-m-d'));
        self::assertSame('1932-11-29', (new PartialDate(1932, 11, 29))->toDate()->format('Y-m-d'));
        self::assertNull((new PartialDate(1931, 2, 30))->toDate(), 'a day that does not exist');
        self::assertSame('2019-09-26', (string) PartialDate::fromDate(new \DateTimeImmutable('2019-09-26')));
    }

    public function testAPeriod(): void
    {
        self::assertSame('1853', (string) Period::year(1853));
        self::assertTrue(Period::year(1853)->isYear());
        self::assertSame('1850/1860', (string) Period::years(1850, 1860));
        self::assertSame('1970/…', (string) Period::since(1970));
        self::assertSame('1851/1855', (string) Period::around(1853));
        self::assertTrue(Period::day(new PartialDate(1853, 3, 30))->isDay());

        self::assertTrue(Period::since(1970)->contains(new PartialDate(2019, 9, 26)));
        self::assertFalse(Period::since(1970)->contains(new PartialDate(1969)));
        self::assertTrue(Period::years(1850, 1860)->contains(new PartialDate(1860, 12)));
        self::assertFalse(Period::years(1850, 1860)->contains(new PartialDate(1861)));
    }

    public function testCountryCodes(): void
    {
        self::assertSame('FR', CountryCode::alpha2('FRA'));
        self::assertSame('NL', CountryCode::alpha2('nld'));
        self::assertSame('GB', CountryCode::alpha2('gb'));
        self::assertSame('PL', CountryCode::alpha2('POL'));
        self::assertNull(CountryCode::alpha2('XXX'));
        self::assertNull(CountryCode::alpha2('ZZ'));
        self::assertNull(CountryCode::alpha2(null));
        self::assertSame('DEU', CountryCode::alpha3('de'));
        self::assertCount(249, CountryCode::ALPHA3);
        self::assertCount(249, array_unique(CountryCode::ALPHA3));
    }

    public function testAQuery(): void
    {
        $query = new CivilQuery(familyName: 'Chirac', givenName: 'Jacques', country: ' fr ', kinds: [CivilRecordKind::DEATH], limit: 500, page: 0);

        self::assertSame('Jacques Chirac', $query->name());
        self::assertSame('FR', $query->country);
        self::assertSame(100, $query->limit);
        self::assertSame(1, $query->page);
        self::assertTrue($query->wants(CivilRecordKind::DEATH));
        self::assertFalse($query->wants(CivilRecordKind::BIRTH));
        self::assertTrue((new CivilQuery(familyName: 'Chirac'))->wants(CivilRecordKind::BIRTH), 'no kind asked: any');
        self::assertTrue($query->inCountries('NL', 'FR'));
        self::assertFalse($query->inCountries('NL'));
        self::assertFalse((new CivilQuery(place: 'Paris'))->hasName());
        self::assertNotSame($query->key(), (new CivilQuery(familyName: 'Chirac', givenName: 'Jacques', country: 'FR'))->key());
        self::assertSame($query->key(), (new CivilQuery(familyName: 'CHIRAC', givenName: 'jacques', country: 'FR', kinds: [CivilRecordKind::DEATH], limit: 100))->key());
    }

    public function testKindsAndSexes(): void
    {
        self::assertSame('BIRT', CivilRecordKind::BIRTH->gedcom());
        self::assertSame('CHR', CivilRecordKind::BAPTISM->gedcom());
        self::assertNull(CivilRecordKind::OTHER->gedcom());
        self::assertSame(Sex::MALE, Sex::fromAny('Man'));
        self::assertSame(Sex::FEMALE, Sex::fromAny('Vrouw'));
        self::assertSame(Sex::FEMALE, Sex::fromAny('F'));
        self::assertSame(Sex::UNKNOWN, Sex::fromAny('Onbekend'));
    }

    public function testWhatTheRegistersInstalledCover(): void
    {
        $omnistate = new Omnistate(civil: [$this->registry('deaths-fr', ['FR'], [CivilRecordKind::DEATH]), $this->registry('acts-nl', ['NL', 'BE'], [CivilRecordKind::BIRTH, CivilRecordKind::DEATH])]);

        self::assertSame(['BE', 'FR', 'NL'], $omnistate->civilCountries());
        self::assertSame(['deaths-fr', 'acts-nl'], array_map(static fn ($r) => $r->name(), $omnistate->civilRegistries()));
        self::assertSame(['acts-nl'], array_map(static fn ($r) => $r->name(), $omnistate->civilRegistries('be')));
        self::assertSame(['acts-nl'], array_map(static fn ($r) => $r->name(), $omnistate->civilRegistries(kind: CivilRecordKind::BIRTH)));
        self::assertSame([], $omnistate->civilRegistries('JP'));
        self::assertSame('acts-nl', $omnistate->civilRegistry('acts-nl')?->name());
        self::assertNull($omnistate->civilRegistry('koseki'));
        self::assertSame([], (new Omnistate())->civilCountries());
    }

    public function testRecordsAreAskedOfTheRegistersTheQuestionIsFor(): void
    {
        $fr = $this->registry('deaths-fr', ['FR'], [CivilRecordKind::DEATH]);
        $nl = $this->registry('acts-nl', ['NL', 'BE'], [CivilRecordKind::BIRTH, CivilRecordKind::DEATH]);
        $omnistate = new Omnistate(civil: [$fr, $nl], cache: new ArrayAdapter());

        $all = $omnistate->civilRecords(new CivilQuery(familyName: 'Vasseur'));
        self::assertSame(['deaths-fr', 'acts-nl'], array_map(static fn (CivilRecord $r) => $r->source, $all), 'each one\'s answers, in the order given');

        $dutch = $omnistate->civilRecords(new CivilQuery(familyName: 'Vasseur', country: 'NL'));
        self::assertSame(['acts-nl'], array_map(static fn (CivilRecord $r) => $r->source, $dutch));

        $named = $omnistate->civilRecords(new CivilQuery(familyName: 'Vasseur'), 'deaths-fr');
        self::assertSame(['deaths-fr'], array_map(static fn (CivilRecord $r) => $r->source, $named));
        self::assertSame(1, $fr->searches, 'the same question again: from the cache');
        self::assertSame(2, $nl->searches);

        $record = $omnistate->civilRecord('deaths-fr', 'D-1');
        self::assertSame('Henri Vasseur', $record?->principal()?->name());
        self::assertSame('Illkirch, FR', (string) $record->place);
        self::assertSame('Archives fictives, registre 12', (string) $record->archive);
        self::assertNull($omnistate->civilRecord('deaths-fr', 'nobody'));
    }

    public function testNoRegisterForTheQuestion(): void
    {
        $omnistate = new Omnistate(civil: [$this->registry('deaths-fr', ['FR'], [CivilRecordKind::DEATH])]);

        foreach ([fn () => $omnistate->civilRecords(new CivilQuery(familyName: 'Tanaka', country: 'JP')),
            fn () => $omnistate->civilRecords(new CivilQuery(familyName: 'Vasseur'), 'acts-nl'),
            fn () => $omnistate->civilRecord('acts-nl', 'x'),
            fn () => (new Omnistate())->civilRecords(new CivilQuery(familyName: 'Vasseur'))] as $ask) {
            try {
                $ask();
                self::fail('not supported');
            } catch (NotSupportedException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testARegisterDownIsNotAnEmptyAnswerAndIsNotKept(): void
    {
        $down = $this->registry('deaths-fr', ['FR'], [CivilRecordKind::DEATH]);
        $down->down = true;
        $omnistate = new Omnistate(civil: [$down, $this->registry('acts-nl', ['NL'], [CivilRecordKind::DEATH])], cache: new ArrayAdapter());

        try {
            $omnistate->civilRecords(new CivilQuery(familyName: 'Vasseur'));
            self::fail('unavailable');
        } catch (UnavailableException) {
        }
        // asked one by one, the other still answers
        self::assertCount(1, $omnistate->civilRecords(new CivilQuery(familyName: 'Vasseur'), 'acts-nl'));

        $down->down = false;
        self::assertCount(1, $omnistate->civilRecords(new CivilQuery(familyName: 'Vasseur'), 'deaths-fr'), 'the failure was not cached');
    }

    public function testTheBundleRegistersTheCivilRegistersInstalled(): void
    {
        if (!class_exists(MatchId::class) || !class_exists(OpenArchieven::class) || !class_exists(NationalArchivesUk::class) || !class_exists(Nara::class)) {
            self::markTestSkipped('The civil registry packages are not installed.');
        }
        $container = new ContainerBuilder();
        $container->register('http_client', MockHttpClient::class)->setPublic(true)->setSynthetic(true);
        $container->register('cache.app', ArrayAdapter::class);
        $bundle = new OmnistateBundle();
        $container->registerExtension($bundle->getContainerExtension());
        $container->loadFromExtension('omnistate', ['matchid' => ['token' => 't'], 'nara' => ['api_key' => 'k']]);
        $container->compile();
        $headers = [];
        $container->set('http_client', new MockHttpClient(function (string $method, string $url, array $options) use (&$headers) {
            $headers = $options['headers'];

            return new MockResponse('{"response":{"total":0,"persons":[]}}');
        }));
        $omnistate = $container->get(Omnistate::class);

        self::assertSame(['matchid', 'openarchieven', 'national-archives-uk', 'nara'], array_map(static fn ($r) => $r->name(), $omnistate->civilRegistries()));
        self::assertSame(['BE', 'FR', 'GB', 'NL', 'SR', 'US'], $omnistate->civilCountries());
        self::assertSame(['matchid', 'openarchieven'], array_map(static fn ($r) => $r->name(), $omnistate->civilRegistries('FR')));
        self::assertSame([], $omnistate->civilRecords(new CivilQuery(familyName: 'Nobody', kinds: [CivilRecordKind::DEATH]), 'matchid'));
        self::assertContains('Authorization: Bearer t', $headers);

        $omnistate->civilRecords(new CivilQuery(familyName: 'Nobody'), 'nara');
        self::assertContains('x-api-key: k', $headers, 'the key configured, in its header');
    }

    private function registry(string $name, array $countries, array $kinds): CivilRegistryInterface
    {
        return new class($name, $countries, $kinds) implements CivilRegistryInterface {
            public int $searches = 0;
            public bool $down = false;

            public function __construct(private string $name, private array $countries, private array $kinds)
            {
            }

            public function name(): string
            {
                return $this->name;
            }

            public function countries(): array
            {
                return $this->countries;
            }

            public function kinds(): array
            {
                return $this->kinds;
            }

            public function period(): ?Period
            {
                return null;
            }

            public function hasImages(): bool
            {
                return false;
            }

            public function supports(CivilQuery $query): bool
            {
                return $query->hasName() && $query->inCountries(...$this->countries) && $query->wantsAny(...$this->kinds);
            }

            public function search(CivilQuery $query): array
            {
                ++$this->searches;
                if ($this->down) {
                    throw new UnavailableException('down', 30);
                }

                return [$this->find('D-1')];
            }

            public function find(string $identifier): ?CivilRecord
            {
                return 'D-1' !== $identifier ? null : new CivilRecord(
                    'D-1', CivilRecordKind::DEATH, $this->name, new PartialDate(1969, 4, 2), new Place('Illkirch', country: 'FR'),
                    [new CivilPerson('Vasseur', ['Henri'], Sex::MALE, 'Deceased')], new Archive('Archives fictives', reference: 'registre 12'),
                );
            }
        };
    }
}
