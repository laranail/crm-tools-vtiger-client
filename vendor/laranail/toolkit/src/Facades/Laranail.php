<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Facades;

use Illuminate\Support\Facades\Facade;
use Simtabi\Laranail\Toolkit\ToolkitManager;

/**
 * The familiar `Laranail` entry point, re-introduced as a clean alias.
 *
 * This resolves the SAME {@see ToolkitManager} as the {@see Toolkit} facade —
 * one manager, two facade names — so `Laranail::route()` is identical to
 * `Toolkit::route()` with no duplicated logic. It is NOT the legacy 48-method
 * service-locator; it exposes only the toolkit's typed module accessors.
 *
 * @method static \Simtabi\Laranail\Toolkit\Modules\Archiver\ArchiverServiceInterface                 archiver()
 * @method static \Simtabi\Laranail\Toolkit\Services\Contracts\RouteServiceInterface                  route()
 * @method static \Simtabi\Laranail\Toolkit\Services\Contracts\ValidationServiceInterface             validation()
 * @method static \Simtabi\Laranail\Toolkit\Services\Contracts\SessionServiceInterface                session()
 * @method static \Simtabi\Laranail\Toolkit\Services\Contracts\FileServiceInterface                   file()
 * @method static \Simtabi\Laranail\Toolkit\Services\Contracts\SystemServiceInterface                 system()
 * @method static \Simtabi\Laranail\Toolkit\Services\ModelService                                     model()
 * @method static \Simtabi\Laranail\Toolkit\Services\Contracts\HttpConfigurationServiceInterface      http()
 * @method static \Simtabi\Laranail\Toolkit\Services\Contracts\AuthenticationContextServiceInterface  auth()
 * @method static \Simtabi\Laranail\Toolkit\ToolkitManager                                            withGuard(?string $guard = null)
 * @method static \Illuminate\Contracts\Auth\Authenticatable|\Illuminate\Database\Eloquent\Model|null user(?string $guard = null)
 * @method static TUser|null                                                                          userAs<TUser of \Illuminate\Contracts\Auth\Authenticatable>(class-string<TUser> $model, ?string $guard = null)
 * @method static \Illuminate\Contracts\Auth\Authenticatable|\Illuminate\Database\Eloquent\Model      userOrFail(?string $guard = null)
 * @method static \Simtabi\Laranail\Toolkit\Services\Contracts\CacheRepositoryInterface               cache()
 * @method static \Simtabi\Laranail\Toolkit\Support\RuntimeConfigurator                               runtime()
 * @method static \Simtabi\Laranail\Toolkit\Services\Contracts\LoggerServiceInterface                 log()
 * @method static \Simtabi\Laranail\Toolkit\Services\Contracts\SettingsStoreInterface                 settings()
 * @method static \Simtabi\Laranail\Toolkit\Services\Contracts\RateLimiterServiceInterface            rateLimiter()
 * @method static \Simtabi\Laranail\Toolkit\Services\Contracts\SchedulerServiceInterface              scheduler()
 * @method static \Simtabi\Laranail\Toolkit\Modules\Livewire\LivewireServiceInterface                 livewire()
 * @method static \Simtabi\Laranail\Toolkit\Modules\Security\Token                                    token()
 * @method static \Simtabi\Laranail\Toolkit\Modules\Security\Password                                 password()
 * @method static \Simtabi\Laranail\Toolkit\Modules\Security\Passphrase                               passphrase()
 *
 * @see ToolkitManager
 * @see Toolkit
 */
class Laranail extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ToolkitManager::class;
    }
}
