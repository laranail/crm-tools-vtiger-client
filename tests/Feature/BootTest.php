<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;
use Simtabi\Laranail\CrmTools\VtigerClient\VtWsClient;
use Simtabi\Laranail\CrmTools\VtigerClient\Helpers\Helpers;
use Simtabi\Laranail\CrmTools\VtigerClient\Providers\VtWsClientServiceProvider;

it('boots', function (): void {
    expect(app()->getLoadedProviders())->toHaveKey(VtWsClientServiceProvider::class);
});

/**
 * `Helpers::PACKAGE_NAME` was the bare `vtiger-rest-api-client` and served as the config root in
 * twelve places. Config keys are a flat global namespace; the consuming application claiming the
 * same string silently wins.
 */
it('reads config under vendor and slug, never a bare one', function (): void {
    expect(Helpers::CONFIG_KEY)->toBe('laranail.crm-tools-vtiger-client')
        ->and(Config::get('laranail.crm-tools-vtiger-client'))->toBeArray()
        ->and(Config::get('vtiger-rest-api-client'))->toBeNull();
});

it('scopes the package name used for cache and translations', function (): void {
    expect(Helpers::PACKAGE_NAME)->toBe('laranail-crm-tools-vtiger-client');

    expect(Lang::getLoader()->namespaces())
        ->toHaveKey('laranail/crm-tools-vtiger-client')
        ->and(Lang::getLoader()->namespaces())->not->toHaveKey('vtiger-rest-api-client');
});

/**
 * The old provider spelled its tags `vtiger-rest-api-client:config` -- a colon form nothing else in
 * the family uses, and bare besides.
 */
it('publishes under vendor-scoped tags', function (): void {
    $tags = ServiceProvider::publishableGroups();

    expect($tags)->toContain('laranail::crm-tools-vtiger-client-config')
        ->not->toContain('vtiger-rest-api-client:config');
});

/**
 * Blocked upstream, not here.
 *
 * Constructing the client reaches `Session`, which calls `pheg()->transfigure()`, and
 * `Simtabi\Pheg\Toolbox\Transfigures\Transfigure::__construct()` does `new Validators()` from
 * `Simtabi\Enekia\Vanilla` -- a namespace `simtabi/pheg` uses but **does not declare**. Its
 * composer.json has no `simtabi/enekia` requirement and no `repositories` entry, so the class is
 * never installed and the constructor fatals with "Class not found".
 *
 * That is a defect in `simtabi/pheg`, which is a separate package in another org. Kept as a skip
 * rather than deleted so the gap stays visible: this is the one thing about this package still
 * unproven.
 */
it('resolves the client', function (): void {
    expect(app(VtWsClient::class))->toBeInstanceOf(VtWsClient::class);
})->skip('simtabi/pheg uses Simtabi\Enekia\Vanilla\Validators without declaring simtabi/enekia.');
