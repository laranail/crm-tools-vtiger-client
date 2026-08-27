<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Abstract JSON-shape controller for package APIs.
 *
 * Returns JsonResponse, typed throughout, with status codes via
 * Response::HTTP_* constants. Status-code mutation trusts the caller
 * rather than warning on questionable values.
 */
abstract class ApiController extends WebController
{
    protected int $statusCode = Response::HTTP_OK;

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function setStatusCode(int $statusCode): static
    {
        $this->statusCode = $statusCode;

        return $this;
    }

    /**
     * Generic JSON response with the currently-configured status code.
     *
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     */
    protected function respondWithArray(array $payload, array $headers = []): JsonResponse
    {
        return response()->json($payload, $this->statusCode, $headers);
    }

    protected function respondWithSuccess(int $statusCode = Response::HTTP_OK): JsonResponse
    {
        return $this->setStatusCode($statusCode)
            ->respondWithArray(['success' => true]);
    }

    /**
     * Emit an error payload at the currently-configured status code.
     *
     * Call setStatusCode() (or one of the errorXxx helpers below) first
     * to avoid sending a 200-with-error response.
     */
    protected function respondWithError(string $message): JsonResponse
    {
        return $this->respondWithArray(['message' => $message]);
    }

    public function errorForbidden(string $message = 'Forbidden'): JsonResponse
    {
        return $this->setStatusCode(Response::HTTP_FORBIDDEN)
            ->respondWithError($message);
    }

    public function errorInternalError(string $message = 'Internal Error'): JsonResponse
    {
        return $this->setStatusCode(Response::HTTP_INTERNAL_SERVER_ERROR)
            ->respondWithError($message);
    }

    public function errorNotFound(string $message = 'Resource Not Found'): JsonResponse
    {
        return $this->setStatusCode(Response::HTTP_NOT_FOUND)
            ->respondWithError($message);
    }

    public function errorUnauthorized(string $message = 'Unauthorized'): JsonResponse
    {
        return $this->setStatusCode(Response::HTTP_UNAUTHORIZED)
            ->respondWithError($message);
    }
}
