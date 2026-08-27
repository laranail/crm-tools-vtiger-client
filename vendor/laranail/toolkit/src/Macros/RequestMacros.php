<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Macros;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the toolkit's general-purpose Request macros.
 *
 * Trivial one-to-one aliases of native Request methods (userAgent(), secure(),
 * ips(), bearerToken(), ...) are intentionally omitted, as are the legacy
 * macros that recursed into the native method of the same name.
 */
final class RequestMacros extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerRequestMacros();
    }

    private function registerRequestMacros(): void
    {
        Request::macro('expectsJsonOrAjax', function (): bool {
            /** @var Request $this */
            return $this->wantsJson() || $this->ajax();
        });

        Request::macro('isBot', function (): bool {
            /** @var Request $this */
            $userAgent = strtolower($this->userAgent() ?? '');
            $bots = ['bot', 'crawl', 'slurp', 'spider', 'mediapartners'];

            return array_any($bots, fn ($bot) => str_contains($userAgent, $bot));
        });

        Request::macro('isFromMobile', function (): bool {
            /** @var Request $this */
            $userAgent = strtolower($this->userAgent() ?? '');
            $mobileKeywords = ['mobile', 'android', 'iphone', 'ipad', 'windows phone'];

            return array_any($mobileKeywords, fn ($keyword) => str_contains($userAgent, $keyword));
        });

        Request::macro('hasFiles', function (array $keys): bool {
            /** @var Request $this */
            $request = $this;

            return array_all($keys, fn ($key) => $request->hasFile($key));
        });

        Request::macro('hasValidFile', function (string $key): bool {
            /** @var Request $this */
            if (!$this->hasFile($key)) {
                return false;
            }

            $file = $this->file($key);

            return $file instanceof UploadedFile && $file->isValid();
        });

        Request::macro('getReferer', function (?string $default = null): ?string {
            /** @var Request $this */
            return $this->header('referer', $default);
        });

        Request::macro('isFromDomain', function (string $domain): bool {
            /** @var Request $this */
            $referer = $this->header('referer');

            if ($referer === null || $referer === '') {
                return false;
            }

            // Compare the parsed host, not a substring — otherwise
            // https://evil.com/?ref=trusted.com would match 'trusted.com'.
            $host = parse_url($referer, PHP_URL_HOST);

            return is_string($host) && ($host === $domain || str_ends_with($host, '.' . $domain));
        });

        Request::macro('isJsonRequest', function (): bool {
            /** @var Request $this */
            return $this->isJson() || $this->wantsJson();
        });

        Request::macro('onlyFilled', function (array $keys): array {
            /** @var Request $this */
            return array_filter($this->only($keys), filled(...));
        });

        Request::macro('hasAny', function (array $keys): bool {
            /** @var Request $this */
            $request = $this;

            return array_any($keys, fn ($key) => $request->has($key));
        });

        Request::macro('mergeIfMissing', function (array $values): Request {
            /** @var Request $this */
            $request = $this;

            foreach ($values as $key => $value) {
                if (!$request->has($key)) {
                    $request->merge([$key => $value]);
                }
            }

            return $request;
        });
    }
}
