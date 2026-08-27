<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Simtabi\Laranail\Toolkit\Traits\ApiResponseTrait;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Thrown when an authentication-related operation fails.
 *
 * Built on the rich {@see LaranailException} base, so it carries structured
 * context, a UI-facing message and an HTTP status. It is *self-rendering*: when
 * the request expects JSON, Laravel's framework handler (L11+ calls `render()`
 * directly on the exception — no custom Handler subclass required) emits a
 * standardised error envelope mirroring {@see ApiResponseTrait}.
 */
class AuthenticationException extends LaranailException
{
    /**
     * Default HTTP status for authentication failures.
     */
    private const DEFAULT_STATUS = HttpResponse::HTTP_UNAUTHORIZED;

    /**
     * No guard was supplied before using the auth helper.
     */
    public static function missingGuard(): self
    {
        return new self(
            message: 'You must provide a guard before you can use the authentication helper.',
            code: 2001,
            userMessage: 'Authentication guard not specified.',
            status: self::DEFAULT_STATUS,
        );
    }

    /**
     * The supplied guard is not a known/configured guard.
     *
     * @param string $guard The invalid guard name.
     */
    public static function invalidGuard(string $guard): self
    {
        return new self(
            message: "Invalid authentication guard: {$guard}",
            code: 2002,
            context: ['guard' => $guard],
            userMessage: 'Invalid authentication configuration.',
            status: self::DEFAULT_STATUS,
        );
    }

    /**
     * The current user is not authenticated (optionally on a named guard).
     *
     * @param string|null $guard The guard the user failed to authenticate on.
     */
    public static function unauthenticated(?string $guard = null): self
    {
        return new self(
            message: 'User is not authenticated.' . ($guard !== null ? " (guard: {$guard})" : ''),
            code: 2003,
            context: array_filter(['guard' => $guard], static fn (mixed $value): bool => $value !== null),
            userMessage: 'Please log in to continue.',
            status: self::DEFAULT_STATUS,
        );
    }

    /**
     * Render the exception as a JSON envelope when the request expects JSON.
     *
     * Returning `false` for non-JSON requests defers to Laravel's default
     * rendering (e.g. a redirect to the login route / an HTML error page), so
     * the toolkit never hijacks web responses.
     */
    public function render(Request $request): JsonResponse|false
    {
        if (!$request->expectsJson()) {
            return false;
        }

        $status = $this->resolveStatus();

        $payload = [
            'success' => false,
            'message' => $this->getUserMessage() ?? $this->getMessage(),
            'errors' => $this->getContext(),
        ];

        if ((bool) config('app.debug')) {
            $payload['debug'] = $this->toArray();
        }

        return new JsonResponse($payload, $status);
    }

    /**
     * Resolve a valid HTTP status, defaulting to 401 for any out-of-range value.
     */
    private function resolveStatus(): int
    {
        $status = $this->getStatus();

        if ($status === null || $status < 100 || $status > 599) {
            return self::DEFAULT_STATUS;
        }

        return $status;
    }
}
