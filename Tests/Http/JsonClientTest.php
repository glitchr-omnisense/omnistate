<?php

namespace Omnistate\Tests\Http;

use Omnisense\Contracts\Policy\Backoff;
use Omnisense\Contracts\Policy\InMemoryRateLimiter;
use Omnistate\Exception\OmnistateException;
use Omnistate\Exception\UnavailableException;
use Omnistate\Exception\UsageLimitReachedException;
use Omnistate\Http\JsonClient;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/** The family's rule on errors, on omnisense/contracts' JsonClient. */
final class JsonClientTest extends TestCase
{
    public function testAnAnswerAndNotFound(): void
    {
        $client = new JsonClient(new MockHttpClient([new MockResponse('<a/>', ['response_headers' => ['content-type' => 'application/xml']]), new MockResponse('', ['http_code' => 404])]), 'Register');

        self::assertSame('<a/>', $client->answer('GET', 'https://register.example/a')?->body);
        self::assertNull($client->answer('GET', 'https://register.example/b'));
    }

    public function testTooManyCallsIsUnavailableWithItsWait(): void
    {
        $client = new JsonClient(new MockHttpClient(new MockResponse('', ['http_code' => 429, 'response_headers' => ['retry-after' => '12']])), 'Register');

        try {
            $client->answer('GET', 'https://register.example/a?key=secret-value');
            self::fail('A 429 was an answer.');
        } catch (UnavailableException $e) {
            self::assertSame(12, $e->retryAfter);
            self::assertTrue($e->isTooManyCalls());
            self::assertSame('Register answered 429 Too Many Requests: try again in 12 s.', $e->getMessage());
        }
    }

    /** @return iterable<string, array{MockResponse, class-string<\Throwable>, ?int}> */
    public static function failures(): iterable
    {
        yield '401' => [new MockResponse('{"error":"no"}', ['http_code' => 401]), UnavailableException::class, 401];
        yield '403' => [new MockResponse('', ['http_code' => 403]), UnavailableException::class, 403];
        yield '500' => [new MockResponse('oops', ['http_code' => 500]), UnavailableException::class, 500];
        yield '503 (an HTML page)' => [new MockResponse('<html><body>Down</body></html>', ['http_code' => 503]), UnavailableException::class, 503];
        yield 'unreachable' => [new MockResponse('', ['error' => 'Could not resolve host']), UnavailableException::class, null];
        yield '400' => [new MockResponse('{"error":"bad key"}', ['http_code' => 400]), OmnistateException::class, null];
    }

    /** @param class-string<\Throwable> $class */
    #[DataProvider('failures')]
    public function testTheFamilysRuleOnErrors(MockResponse $response, string $class, ?int $status): void
    {
        $client = new JsonClient(new MockHttpClient($response), 'Register');

        try {
            $client->answer('GET', 'https://register.example/a?api_key=secret-value');
            self::fail('A failure was an answer.');
        } catch (\Throwable $e) {
            self::assertSame($class, $e::class);
            self::assertStringNotContainsString('secret-value', $e->getMessage());
            if ($e instanceof UnavailableException) {
                self::assertSame($status, $e->status);
            }
        }
    }

    public function testABodyThatIsNotJsonIsUnavailable(): void
    {
        $client = new JsonClient(new MockHttpClient(new MockResponse('<html>Maintenance</html>')), 'Register');

        $this->expectException(UnavailableException::class);
        $client->get('https://register.example/a');
    }

    public function testAWaitTheRegisterAskedIsAUsageLimitReachedException(): void
    {
        $backoff = new Backoff(new InMemoryRateLimiter(), 'register');
        $client = new JsonClient(new MockHttpClient([new MockResponse('', ['http_code' => 503, 'response_headers' => ['retry-after' => '20']]), new MockResponse('{}')]), 'Register', [], $backoff);

        try {
            $client->get('https://register.example/a');
            self::fail('A 503 was an answer.');
        } catch (UnavailableException $e) {
            self::assertNotInstanceOf(UsageLimitReachedException::class, $e);
        }
        try {
            $client->get('https://register.example/a');
            self::fail('The wait was not kept.');
        } catch (UsageLimitReachedException $e) {
            self::assertSame(20, $e->retryAfter);
        }
    }
}
