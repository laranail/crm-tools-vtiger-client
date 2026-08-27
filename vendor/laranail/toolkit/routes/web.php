<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'tidy', 'as' => 'tidy.', 'middleware' => ['web']], function () {

    Route::get('/', ['name' => 'all', 'uses' => function () {
        Artisan::call('optimize');
        Artisan::call('route:cache');
        Artisan::call('cache:clear');
        Artisan::call('view:clear');
        Artisan::call('config:cache');
        Artisan::call('clear-compiled');

        return 'All Cache, Route, Config, and View files have been purged successfully!';
    }]);

    Route::get('/optimize', ['name' => 'optimize', 'uses' => function () {
        Artisan::call('optimize');

        return 'Application optimized successfully!';
    }]);

    Route::get('/route', ['name' => 'route', 'uses' => function () {
        Artisan::call('route:cache');

        return 'Application route cache cleared successfully!';
    }]);

    Route::get('/cache', ['name' => 'cache', 'uses' => function () {
        Artisan::call('cache:clear');

        return 'Application cache cleared successfully!';
    }]);

    Route::get('/view', ['name' => 'view', 'uses' => function () {
        Artisan::call('view:clear');

        return 'Application views cleared successfully!';
    }]);

    Route::get('/config', ['name' => 'config', 'uses' => function () {
        Artisan::call('config:cache');

        return 'Application config cleared successfully!';
    }]);

    Route::get('/clear-compiled', ['name' => 'clear-compiled', 'uses' => function () {
        Artisan::call('clear-compiled');

        return 'Compiled files cleared successfully!';
    }]);

});
