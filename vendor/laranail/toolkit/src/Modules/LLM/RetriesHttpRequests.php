<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\LLM;

use Illuminate\Support\Facades\Log;

/**
 * Shared retry behaviour for HTTP-based LLM providers.
 *
 * Expects the using class to declare `int $maxRetries` and `int $retryDelay`.
 */
trait RetriesHttpRequests
{
    /**
     * Run the callback, retrying only on retryable failures (transport errors,
     * HTTP 429 and 5xx). Non-retryable failures (e.g. 4xx) fail fast.
     *
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     */
    protected function executeWithRetry(callable $callback, string $provider)
    {
        $max = max(1, $this->maxRetries);
        $attempt = 0;
        $lastException = null;

        while ($attempt < $max) {
            try {
                return $callback();
            } catch (LLMRequestException $e) {
                $lastException = $e;
                $attempt++;

                if (!$e->isRetryable() || $attempt >= $max) {
                    break;
                }

                Log::warning("{$provider} API request failed, retrying...", [
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                ]);

                if ($this->retryDelay > 0) {
                    sleep($this->retryDelay);
                }
            }
        }

        // The loop only exits here via the catch path, so $lastException is
        // set — but make the invariant explicit rather than assumed.
        if (!$lastException instanceof LLMRequestException) {
            throw new \LogicException("{$provider} retry loop exited without a captured exception.");
        }

        Log::error("{$provider} API request failed", [
            'error' => $lastException->getMessage(),
        ]);

        throw $lastException;
    }

    protected function isRetryableStatus(int $status): bool
    {
        return $status === 429 || $status >= 500;
    }

    /**
     * Validate a configured base URL and reject non-HTTP(S) schemes (SSRF guard).
     */
    protected function sanitizeBaseUrl(string $baseUrl): string
    {
        $scheme = strtolower((string) parse_url($baseUrl, PHP_URL_SCHEME));

        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new LLMRequestException("Invalid LLM base URL scheme: [{$scheme}].");
        }

        return rtrim($baseUrl, '/');
    }
}
