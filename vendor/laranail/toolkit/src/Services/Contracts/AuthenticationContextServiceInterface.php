<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Services\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Fluent authentication helper for managing user context across guards.
 */
interface AuthenticationContextServiceInterface
{
    /** Set the tracked user email. */
    public function setUserEmail(?string $email): self;

    /** Set the tracked user identifier. */
    public function setUserId(int|string|null $id): self;

    /** Set the active authentication guard. */
    public function setGuard(?string $guard): self;

    /** Get the tracked user email. */
    public function getUserEmail(): ?string;

    /** Get the tracked user identifier. */
    public function getUserId(): int|string|null;

    /** Get the active authentication guard. */
    public function getGuard(): ?string;

    /**
     * Resolve the currently authenticated user.
     *
     * @param string|null $guard Optional guard override.
     */
    public function getUser(?string $guard = null): Model|Authenticatable|null;

    /**
     * Whether a user is authenticated.
     *
     * @param string|null $guard Optional guard override.
     */
    public function isAuthenticated(?string $guard = null): bool;

    /**
     * Resolve the authenticated user's identifier.
     *
     * @param string|null $guard Optional guard override.
     */
    public function getCurrentUserId(?string $guard = null): int|string|null;
}
