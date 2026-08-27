<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Macros;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\ResponseFactory;
use Illuminate\Support\ServiceProvider;
use Simtabi\Laranail\Toolkit\Support\ApiResponder;
use Simtabi\Laranail\Toolkit\Traits\ApiResponseTrait;

/**
 * Registers the toolkit's Response macros.
 *
 * `success`, `error` and `message` delegate to the canonical
 * {@see ApiResponseTrait} envelope (via
 * {@see ApiResponder}) so there is exactly one place that shapes the API JSON.
 * `pdf` streams raw PDF bytes with the correct headers — unlike the legacy
 * version it never echoes unescaped HTML.
 */
final class ResponseMacros extends ServiceProvider
{
    public function boot(): void
    {
        ResponseFactory::macro('success', fn (mixed $data = null, string $message = 'Request successful.', int $status = 200, array $meta = []): JsonResponse => (new ApiResponder())->success($data, $message, $status, $meta));

        ResponseFactory::macro('error', fn (string $message = 'Bad Request', int $status = 400, array $errors = [], mixed $debug = null): JsonResponse => (new ApiResponder())->error($message, $status, $errors, $debug));

        // message(): a bodyless acknowledgement — the success envelope, null data.
        ResponseFactory::macro('message', fn (string $message, int $status = 200): JsonResponse => (new ApiResponder())->success(null, $message, $status));

        ResponseFactory::macro('pdf', function (
            string $pdf,
            string $fileName = 'document.pdf',
            bool $download = false,
        ): Response {
            $disposition = $download ? 'attachment' : 'inline';

            return new Response($pdf, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => $disposition . '; filename="' . addslashes($fileName) . '"',
            ]);
        });
    }
}
