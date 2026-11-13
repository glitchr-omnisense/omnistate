<?php

namespace Omnistate\Tests;

use Omnisense\Contracts\Exception\ExceptionInterface;
use Omnisense\Contracts\Policy\DeclaresUsagePolicyInterface;
use Omnisense\Contracts\Policy\InMemoryRateLimiter;
use Omnisense\Contracts\Policy\InvalidPolicyException;
use Omnisense\Contracts\Policy\Permission;
use Omnisense\Contracts\Policy\RateLimit;
use Omnisense\Contracts\Policy\UsageNotPermitted;
use Omnisense\Contracts\Policy\UsagePolicy;
use Omnistate\Exception\OmnistateException;
use Omnistate\Exception\UnavailableException;
use Omnistate\Exception\UsageLimitReachedException;
use Omnistate\Model\CivilQuery;
use Omnistate\Model\CivilRecord;
use Omnistate\Model\CivilRecordKind;
use Omnistate\Model\Period;
use Omnistate\Omnistate;
use Omnistate\Policy\GuardedCivilRegistry;
use Omnistate\Tests\Policy\FakeCompanies;
use Omnistate\Tests\Policy\FakeDeclaredCompanies;
use Omnistate\Registry\CivilRegistryInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/** Every call to a register goes through its usage policy, as the site tightened it. */
final class PolicyTest extends TestCase
{
    public function testACallOverTheRegistersLimitIsNeverMade(): void
    {
        $registry = $this->companies(UsagePolicy::builder('fake')->limit(RateLimit::perMinute(2, 'company'))->build());
        $omnistate = new Omnistate([$registry]);

        $omnistate->company('552032534');
        $omnistate->company('356000000');
        try {
            $omnistate->company('552100554');
            self::fail('A third call went through.');
        } catch (UnavailableException $e) {
            self::assertInstanceOf(UsageLimitReachedException::class, $e, 'an UnavailableException: ask again later');
            self::assertInstanceOf(ExceptionInterface::class, $e, 'omnisense/contracts\' interface catches it too');
            self::assertSame('fake\'s usage policy allows 2 a minute for company: reached, try again in 60 s.', $e->getMessage());
            self::assertSame(60, $e->retryAfter);
            self::assertSame('fake', $e->provider);
            self::assertSame(2, $e->limit?->max);
        }
        self::assertCount(2, $registry->asked, 'the third was not asked');
        self::assertSame(0, $omnistate->guard('fake')->remaining('company'));
        self::assertNull($omnistate->guard('fake')->remaining('search'), 'search is not counted');
        self::assertSame([], $omnistate->search('danone'), 'search() still answers');
    }

    public function testAnAnswerKeptInTheCacheIsNotACall(): void
    {
        $registry = $this->companies(UsagePolicy::builder('fake')->limit(RateLimit::perMinute(1, 'company'))->build());
        $omnistate = new Omnistate([$registry], cache: new ArrayAdapter());

        $omnistate->company('552032534');
        $omnistate->company('552032534');
        self::assertCount(1, $registry->asked);
        self::assertSame(0, $omnistate->guard('fake')->remaining('company'));
    }

    public function testATooManyCallsHoldsEveryCallToThatRegisterForItsRetryAfter(): void
    {
        $registry = $this->companies(UsagePolicy::unknown('fake'), new UnavailableException('fake: too many calls.', 30, null, 429));
        $omnistate = new Omnistate([$registry]);

        try {
            $omnistate->company('552032534');
            self::fail('A 429 was an answer.');
        } catch (UnavailableException $e) {
            self::assertNotInstanceOf(UsageLimitReachedException::class, $e, 'the register\'s own failure');
            self::assertTrue($e->isTooManyCalls());
        }
        try {
            $omnistate->search('danone');
            self::fail('The call after a 429 was made.');
        } catch (UsageLimitReachedException $e) {
            self::assertSame(30, $e->retryAfter);
            self::assertStringContainsString('fake asked to wait', $e->getMessage());
        }
        self::assertCount(1, $registry->asked, 'held without asking');
    }

    public function testAnUnavailabilityThatIsNoTooManyCallsHoldsNothing(): void
    {
        $registry = $this->companies(UsagePolicy::unknown('fake'), new UnavailableException('A member state is closed.', 600));
        $omnistate = new Omnistate([$registry]);

        foreach ([1, 2] as $attempt) {
            try {
                $omnistate->company('552032534');
            } catch (UnavailableException $e) {
                self::assertNotInstanceOf(UsageLimitReachedException::class, $e);
            }
        }
        self::assertCount(2, $registry->asked, 'asked again: only a 429 holds the calls');
    }

    public function testTheLimiterIsShared(): void
    {
        $limiter = new InMemoryRateLimiter();
        $policy = UsagePolicy::builder('fake')->limit(RateLimit::perDay(1, 'company'))->build();
        (new Omnistate([$this->companies($policy)], limiter: $limiter))->company('552032534');

        $this->expectException(UsageLimitReachedException::class);
        (new Omnistate([$this->companies($policy)], limiter: $limiter))->company('552032534');
    }

    public function testASiteTightensARegistersPolicyAndNeverLoosensIt(): void
    {
        $policy = UsagePolicy::builder('fake')->limit(RateLimit::perMinute(10, 'company'))->attribution('Source : Fake', 'https://fake.example')->build();
        $registry = $this->companies($policy);
        $omnistate = new Omnistate([$registry], policies: ['fake' => ['rateLimits' => [['max' => 1, 'window' => 'minute', 'operations' => ['company']]], 'rights' => ['caching' => ['permission' => 'forbidden']]]]);

        self::assertCount(2, $omnistate->policy('fake')->rateLimits ?? []);
        self::assertSame(Permission::Forbidden, $omnistate->policy('fake')->rights->caching->permission);
        self::assertSame(0, $omnistate->guard('fake')->cacheTtl(86400), 'no answer kept: the site said so');
        self::assertSame('Source : Fake', $omnistate->policies()['fake']->rights->attribution->text);
        $omnistate->company('552032534');
        try {
            $omnistate->company('356000000');
            self::fail('The site\'s limit let a second call through.');
        } catch (UsageLimitReachedException $e) {
            self::assertSame(1, $e->limit?->max);
        }

        $looser = new Omnistate([$this->companies($policy)], policies: ['fake' => ['rateLimits' => [['max' => 100, 'window' => 'minute', 'operations' => ['company']]]]]);
        $this->expectException(InvalidPolicyException::class);
        $looser->policy('fake');
    }

    public function testAUseTheTermsForbidIsRefusedAtOnce(): void
    {
        $registry = $this->companies(UsagePolicy::builder('fake')->commercial(Permission::Forbidden)->build());
        $omnistate = new Omnistate([$registry], usage: ['commercial' => true]);

        try {
            $omnistate->company('552032534');
            self::fail('A commercial site asked a non-commercial register.');
        } catch (UsageNotPermitted $e) {
            self::assertSame('fake\'s usage policy does not allow this site: commercial use is forbidden.', $e->getMessage());
        }
        self::assertSame([], $registry->asked);
    }

    public function testARegisterWithoutAPolicyIsUnknownAndLimitsNothing(): void
    {
        $registry = $this->companies(null);
        $omnistate = new Omnistate([$registry]);

        self::assertNull($omnistate->policy('fake')->rateLimits, 'unknown, not "none"');
        self::assertSame('fake', $omnistate->policy('fake')->provider);
        foreach (range(1, 5) as $call) {
            $omnistate->company('552032534');
        }
        self::assertCount(5, $registry->asked);

        $this->expectException(OmnistateException::class);
        $omnistate->guard('nobody');
    }

    public function testACivilRegisterIsGivenBehindItsGuard(): void
    {
        $civil = $this->civil(UsagePolicy::builder('archive')->limit(RateLimit::perSecond(1))->build());
        $omnistate = new Omnistate(civil: [$civil]);

        $registry = $omnistate->civilRegistry('archive');
        self::assertInstanceOf(GuardedCivilRegistry::class, $registry);
        self::assertSame($civil, $registry->inner());
        self::assertSame(['NL'], $registry->countries());
        self::assertInstanceOf(GuardedCivilRegistry::class, $omnistate->civilRegistries('NL')[0]);
        $registry->search(new CivilQuery(familyName: 'Gogh'));
        $this->expectException(UsageLimitReachedException::class);
        $omnistate->civilRegistries()[0]->find('a1');
    }

    private function companies(?UsagePolicy $policy, ?\Throwable $failure = null): FakeCompanies
    {
        return null === $policy ? new FakeCompanies($failure) : new FakeDeclaredCompanies($policy, $failure);
    }

    private function civil(UsagePolicy $policy): CivilRegistryInterface
    {
        return new class($policy) implements CivilRegistryInterface, DeclaresUsagePolicyInterface {
            public function __construct(private readonly UsagePolicy $policy)
            {
            }

            public function usagePolicy(): UsagePolicy
            {
                return $this->policy;
            }

            public function name(): string
            {
                return 'archive';
            }

            public function countries(): array
            {
                return ['NL'];
            }

            public function kinds(): array
            {
                return [CivilRecordKind::BIRTH];
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
                return true;
            }

            public function search(CivilQuery $query): array
            {
                return [];
            }

            public function find(string $identifier): ?CivilRecord
            {
                return null;
            }
        };
    }
}
