<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Support;

use Illuminate\Http\JsonResponse;
use Simtabi\Laranail\Toolkit\Traits\ApiResponseTrait;

/**
 * Public adapter that exposes the canonical {@see ApiResponseTrait} envelope so
 * the Response macros can delegate to it without re-shaping the payload.
 *
 * The trait's methods are `protected` (intended for controllers), so this thin
 * object re-exposes them publicly for the macro layer. There is intentionally no
 * duplicate JSON shaping here — every method forwards to the trait.
 */
final class ApiResponder
{
    use ApiResponseTrait;

    /**
     * @param array<string, mixed> $meta
     */
    public function success(
        mixed $data = null,
        string $message = 'Request successful.',
        int $statusCode = 200,
        array $meta = [],
    ): JsonResponse {
        return $this->successResponse($data, $message, $statusCode, $meta);
    }

    /**
     * @param array<string, mixed> $errors
     */
    public function error(
        string $message = 'Something went wrong.',
        int $statusCode = 500,
        array $errors = [],
        mixed $debug = null,
    ): JsonResponse {
        return $this->errorResponse($message, $statusCode, $errors, $debug);
    }
}
