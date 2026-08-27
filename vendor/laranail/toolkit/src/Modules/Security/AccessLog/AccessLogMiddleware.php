<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Security\AccessLog;

use Closure;
use Illuminate\Http\Request;
use Simtabi\Laranail\Toolkit\Modules\Security\SecurityData;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AccessLogMiddleware
{
    /**
     * Keys whose values are never persisted to the access log.
     *
     * @var list<string>
     */
    private const array DEFAULT_REDACT = [
        'password',
        'password_confirmation',
        'current_password',
        'token',
        '_token',
        'secret',
        'authorization',
        'api_key',
        'access_token',
        'refresh_token',
        'credit_card',
        'card_number',
        'cvv',
        'ssn',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    /**
     * Persist the access log after the response has been sent, so logging never
     * adds latency to — nor (via the try/catch) breaks — the request lifecycle.
     */
    public function terminate(Request $request, Response $response): void
    {
        if (!(bool) config('laranail.toolkit.access_log.enabled', true)) {
            return;
        }

        try {
            AccessLog::create([
                'ip' => $request->ip(),
                'method' => $request->method(),
                // Drop the query string so secrets passed as query params are not stored.
                'url' => $request->url(),
                'user_agent' => $request->userAgent(),
                'request_data' => $this->redact($request->all()),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return array<array-key, mixed>
     */
    private function redact(array $data): array
    {
        $redactKeys = $this->redactKeys();

        foreach ($data as $key => $value) {
            if (in_array(strtolower((string) $key), $redactKeys, true)) {
                $data[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $data[$key] = $this->redact($value);
            }
        }

        return $data;
    }

    /**
     * @return list<string>
     */
    private function redactKeys(): array
    {
        // Default keys come from the merged security data file (publishable /
        // overridable); fall back to the local const if that data is empty.
        $default = SecurityData::redactKeys();
        if ($default === []) {
            $default = self::DEFAULT_REDACT;
        }

        $configured = config('laranail.toolkit.access_log.redact', $default);

        $values = is_array($configured) ? $configured : $default;

        return array_values(array_map(
            static fn (mixed $value): string => strtolower(is_string($value) ? $value : ''),
            $values,
        ));
    }
}
