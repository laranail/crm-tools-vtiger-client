<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Database\Eloquent\Model;
use Simtabi\Laranail\Toolkit\Services\Contracts\AuthenticationContextServiceInterface;
use Simtabi\Laranail\Toolkit\Support\Cast;

/**
 * Fluent authentication helper for managing user context across guards.
 *
 * The auth resolver is injected (no `Auth` facade), so the service is testable
 * without the global container.
 */
class AuthenticationContextService implements AuthenticationContextServiceInterface
{
    private ?string $userEmail = null;

    private int|string|null $userId = null;

    private ?string $guard = null;

    public function __construct(
        private readonly AuthFactory $auth,
    ) {}

    public function setUserEmail(?string $email): self
    {
        $this->userEmail = $email;

        return $this;
    }

    public function setUserId(int|string|null $id): self
    {
        $this->userId = $id;

        return $this;
    }

    public function setGuard(?string $guard): self
    {
        $this->guard = $guard;

        return $this;
    }

    public function getUserEmail(): ?string
    {
        return $this->userEmail;
    }

    public function getUserId(): int|string|null
    {
        return $this->userId;
    }

    public function getGuard(): ?string
    {
        return $this->guard;
    }

    public function getUser(?string $guard = null): Model|Authenticatable|null
    {
        return $this->auth->guard($guard ?? $this->guard)->user();
    }

    public function isAuthenticated(?string $guard = null): bool
    {
        return $this->auth->guard($guard ?? $this->guard)->check();
    }

    public function getCurrentUserId(?string $guard = null): int|string|null
    {
        $identifier = $this->getUser($guard)?->getAuthIdentifier();

        if ($identifier === null) {
            return null;
        }

        return is_int($identifier) ? $identifier : Cast::toString($identifier);
    }
}
