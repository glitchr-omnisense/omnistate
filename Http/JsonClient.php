<?php

namespace Omnistate\Http;

use Omnisense\Contracts\Http\Answer;
use Omnisense\Contracts\Http\Data;
use Omnisense\Contracts\Policy\RateLimitHeaders;
use Omnisense\Contracts\Policy\UsageLimitReached;
use Omnistate\Exception\OmnistateException;
use Omnistate\Exception\UnavailableException;
use Omnistate\Exception\UsageLimitReachedException;

/**
 * A register's calls over the application's HTTP client, with the family's
 * rule on errors (docs/providers.md): omnisense/contracts' JsonClient, its
 * failures as Omnistate's exceptions.
 *
 * - HTTP 401, 403, 429 and 5xx, no answer at all: an UnavailableException
 *   naming the path only (never the query, where a key may sit), its status
 *   kept; a 429's Retry-After (seconds or an HTTP date) is its $retryAfter.
 * - A body that is not what it should be: an UnavailableException too.
 * - Another 4xx (a question the register refuses): an OmnistateException.
 * - 404 is null (get(), request()) or given back (raw(), answer()).
 * - Given a Backoff, a wait the register asked for is a UsageLimitReachedException.
 *
 * answer() is the call a register usually makes: the answer, null on 404, a
 * 429 thrown with its wait.
 */
final class JsonClient extends \Omnisense\Contracts\Http\JsonClient
{
    /** The statuses that mean "ask again later". */
    private const UNAVAILABLE = [401, 403, 408, 425, 429];

    /**
     * Any call, its answer as it came - XML, JSON, anything -, null when
     * the register answers 404.
     *
     * @param array<string, mixed>               $options json, body or query, as Symfony's HttpClient takes them
     * @param array<string, string|list<string>> $headers
     *
     * @throws UnavailableException a 429 with its wait, 401, 403, 5xx, no answer
     * @throws OmnistateException   another 4xx
     */
    public function answer(string $method, string $url, array $options = [], array $headers = []): ?Answer
    {
        $answer = $this->raw($method, $url, $options, $headers, [404, 429]);
        if (429 === $answer->status) {
            $wait = RateLimitHeaders::wait(429, $answer->headers);

            throw new UnavailableException(\sprintf('%s answered 429 Too Many Requests%s.', $this->provider, null === $wait ? '' : \sprintf(': try again in %d s', $wait)), $wait, null, 429);
        }

        return 404 === $answer->status ? null : $answer;
    }

    public function get(string $url, array $query = [], array $headers = []): ?Data
    {
        try {
            return parent::get($url, $query, $headers);
        } catch (UsageLimitReached $e) {
            throw UsageLimitReachedException::of($e);
        }
    }

    public function request(string $method, string $url, array $options = [], array $headers = []): ?Data
    {
        try {
            return parent::request($method, $url, $options, $headers);
        } catch (UsageLimitReached $e) {
            throw UsageLimitReachedException::of($e);
        }
    }

    public function raw(string $method, string $url, array $options = [], array $headers = [], array $pass = [404]): Answer
    {
        try {
            return parent::raw($method, $url, $options, $headers, $pass);
        } catch (UsageLimitReached $e) {
            throw UsageLimitReachedException::of($e);
        }
    }

    public function bytes(string $url, array $query = [], array $headers = []): ?string
    {
        try {
            return parent::bytes($url, $query, $headers);
        } catch (UsageLimitReached $e) {
            throw UsageLimitReachedException::of($e);
        }
    }

    protected function answered(string $what, int $status, string $detail): \Throwable
    {
        $detail = mb_strimwidth(trim((string) preg_replace('/\s+/', ' ', strip_tags($detail))), 0, 300, '…');
        if ($status >= 500 || $status < 400 || \in_array($status, self::UNAVAILABLE, true)) {
            return new UnavailableException(\sprintf('%s answered %d for %s%s', $this->provider, $status, $what, '' !== $detail ? ': '.$detail : '.'), null, null, $status < 400 ? null : $status);
        }

        return new OmnistateException(\sprintf('%s refused %s (%d)%s', $this->provider, $what, $status, '' !== $detail ? ': '.$detail : '.'), $status);
    }

    protected function unreachable(string $message, \Throwable $previous): \Throwable
    {
        return new UnavailableException($message, null, $previous);
    }
}
