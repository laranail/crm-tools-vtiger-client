<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Optional global user() helper — OPT-IN
|--------------------------------------------------------------------------
|
| This file is deliberately NOT autoloaded (it is not listed in composer.json
| `autoload.files`), so the toolkit never pollutes the global namespace of every
| app that installs it — the primary API is `Toolkit::user()` / `Laranail::user()`.
|
| Opt in per app by requiring this file (e.g. from a service provider's register()
| or the app's own bootstrap):
|
|     require_once base_path('vendor/laranail/toolkit/helpers/user.php');
|
| It is `function_exists`-guarded, so it silently defers to any existing global
| user() (yours or the framework's) rather than fatally redeclaring.
|
*/

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Simtabi\Laranail\Toolkit\Facades\Toolkit;
use Simtabi\Laranail\Toolkit\ToolkitManager;

if (!function_exists('user')) {
    /**
     * The currently authenticated user (guard-aware, null-safe).
     *
     * A thin global alias for {@see ToolkitManager::user()}.
     */
    function user(?string $guard = null): Authenticatable|Model|null
    {
        return Toolkit::user($guard);
    }
}
