<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Exceptions\Concerns;

use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Throwable;

/**
 * Opt-in reporting/rendering helpers for an application's exception handler.
 *
 * Laravel 11+ moved exception configuration into the application's own
 * `bootstrap/app.php` `withExceptions()` closure, so a package can no longer
 * ship a competing `Handler` subclass. This registrar instead exposes the reusable
 * *logic* from the legacy `LaranailExceptionHandler` as small, composable
 * registrars a consumer can wire in from that closure:
 *
 * ```php
 * use Illuminate\Foundation\Configuration\Exceptions;
 * use Simtabi\Laranail\Toolkit\Exceptions\Concerns\RendersApiExceptions;
 *
 * ->withExceptions(function (Exceptions $exceptions): void {
 *     RendersApiExceptions::register($exceptions);
 * })
 * ```
 *
 * Both registrars are independent — call {@see registerSlackReporter()} and/or
 * {@see registerMethodNotAllowedRenderer()} individually if you only want one.
 *
 * Implemented as a final, all-static registrar (rather than a trait) so it can
 * be called directly from the configuration closure without mixing into a class.
 */
final class RendersApiExceptions
{
    private function __construct() {}

    /**
     * Register every helper this registrar provides on the given configurator.
     */
    public static function register(Exceptions $exceptions): void
    {
        self::registerSlackReporter($exceptions);
        self::registerMethodNotAllowedRenderer($exceptions);
    }

    /**
     * Throttled Slack critical alert for uncaught exceptions.
     *
     * Skips console / maintenance-mode runs, no-ops when no Slack channel is
     * configured, and de-duplicates bursts via a short-lived cache lock so a
     * crash loop does not flood the channel. Returns the exception to Laravel's
     * default logging stack untouched.
     *
     * @param int $throttleMinutes How long to suppress duplicate alerts for.
     */
    public static function registerSlackReporter(Exceptions $exceptions, int $throttleMinutes = 5): void
    {
        $exceptions->report(static function (Throwable $exception) use ($throttleMinutes): void {
            if (app()->runningInConsole() || app()->isDownForMaintenance()) {
                return;
            }

            if (config('logging.channels.slack.url') === null) {
                return;
            }

            $key = 'laranail:toolkit:slack-error-throttle';

            if (Cache::has($key)) {
                return;
            }

            Cache::put($key, true, Carbon::now()->addMinutes($throttleMinutes));

            logger()->channel('slack')->critical(
                $exception->getMessage(),
                self::slackContext($exception),
            );
        });
    }

    /**
     * Render `405 Method Not Allowed` as a JSON envelope for API requests.
     *
     * Defers to Laravel's default (HTML) rendering for non-JSON requests by
     * returning `null`, so web responses are never hijacked.
     */
    public static function registerMethodNotAllowedRenderer(Exceptions $exceptions): void
    {
        $exceptions->render(static function (MethodNotAllowedHttpException $e, Request $request): ?JsonResponse {
            if (!$request->expectsJson()) {
                return null;
            }

            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage() !== '' ? $e->getMessage() : 'Method not allowed.',
                'errors' => [],
            ], HttpResponse::HTTP_METHOD_NOT_ALLOWED);
        });
    }

    /**
     * Build the structured Slack log context for an exception.
     *
     * Form data is only attached for non-GET requests and is JSON-encoded; file
     * paths are made relative to the base path for readability.
     *
     * @return array<string, mixed>
     */
    private static function slackContext(Throwable $exception): array
    {
        $request = request();
        $previous = $exception->getPrevious();

        return array_filter([
            'Request URL' => $request->fullUrl(),
            'Request IP' => $request->ip(),
            'Request Referer' => $request->header('referer'),
            'Request Method' => $request->method(),
            'Request Form Data' => $request->method() !== 'GET'
                ? json_encode($request->input(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                : null,
            'Exception Type' => $exception::class,
            'File Path' => self::relativeLocation($exception->getFile(), $exception->getLine()),
            'Previous File Path' => $previous instanceof Throwable
                ? self::relativeLocation($previous->getFile(), $previous->getLine())
                : null,
        ], static fn (mixed $value): bool => $value !== null && $value !== false);
    }

    /**
     * Format a `file:line` location relative to the application base path.
     */
    private static function relativeLocation(string $file, int $line): string
    {
        return ltrim(sprintf('%s:%d', str_replace(base_path(), '', $file), $line), '/');
    }
}
