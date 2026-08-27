<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Traits;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

trait ApiResponseTrait
{
    /**
     * Send a standardized success response.
     *
     * @param array<string, mixed> $meta
     */
    protected function successResponse(
        mixed $data = null,
        string $message = 'Request successful.',
        int $statusCode = 200,
        array $meta = []
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'meta' => $meta,
        ], $statusCode);
    }

    /**
     * Send a standardized error response with optional debug data.
     *
     * @param array<string, mixed> $errors
     */
    protected function errorResponse(
        string $message = 'Something went wrong.',
        int $statusCode = 500,
        array $errors = [],
        mixed $debug = null
    ): JsonResponse {
        $response = [
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ];

        if ((bool) config('app.debug') && $debug !== null) {
            $response['debug'] = $debug;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Handle exception responses (useful for centralized error handling).
     */
    protected function exceptionResponse(\Throwable $e, int $statusCode = 500): JsonResponse
    {
        Log::error($e->getMessage(), [
            'exception' => $e,
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);

        return $this->errorResponse(
            'Internal server error.',
            $statusCode,
            [],
            [
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'trace' => $e->getTrace(),
            ]
        );
    }

    /**
     * Send a paginated response with meta info.
     *
     * @param LengthAwarePaginator<int, mixed> $paginator
     */
    protected function paginatedResponse(
        LengthAwarePaginator $paginator,
        string $message = 'Data fetched successfully.'
    ): JsonResponse {
        return $this->successResponse(
            $paginator->items(),
            $message,
            200,
            [
                'pagination' => [
                    'total' => $paginator->total(),
                    'count' => $paginator->count(),
                    'per_page' => $paginator->perPage(),
                    'current_page' => $paginator->currentPage(),
                    'total_pages' => $paginator->lastPage(),
                ],
            ]
        );
    }
}
