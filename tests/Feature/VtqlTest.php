<?php

declare(strict_types=1);

use Simtabi\Laranail\CrmTools\VtigerClient\Support\Vtql;
use Simtabi\Laranail\CrmTools\VtigerClient\Services\Entities;

it('quotes a value with an apostrophe the way VTQL expects', function (): void {
    expect(Vtql::literal("O'Connor"))->toBe("'O''Connor'");
});

it('keeps an injection attempt inside one literal', function (): void {
    expect(Vtql::literal("x' OR '1'='1"))->toBe("'x'' OR ''1''=''1'");
});

it('rejects a module or field name that is not an identifier', function (string $name): void {
    Vtql::identifier($name);
})->throws(InvalidArgumentException::class)->with(['Accounts; DELETE', "email1'", 'a b', '']);

it('accepts the operators VTQL supports and rejects anything else', function (): void {
    expect(Vtql::operator('like'))->toBe('LIKE')
        ->and(fn () => Vtql::operator("= '' OR 1=1 --"))->toThrow(InvalidArgumentException::class);
});

/**
 * Entities::getQueryString() interpolated each criterion as `{$param} LIKE '{$value}'`, so a
 * quote in the value escaped the literal.
 */
it('builds query strings with every caller value quoted', function (): void {
    $query = Entities::getQueryString('Contacts', ['lastname' => "O'Connor"]);

    expect($query)->toContain("lastname LIKE 'O''Connor'")
        ->and(fn () => Entities::getQueryString('Contacts', ["lastname' OR 1" => 'x']))->toThrow(InvalidArgumentException::class);
});

it('keeps count(*) usable as a select column', function (): void {
    expect(Vtql::column('count(*)'))->toBe('count(*)')
        ->and(Vtql::column('COUNT( * )'))->toBe('count(*)')
        ->and(Vtql::column('email1'))->toBe('email1');
    expect(fn () => Vtql::column('email1; DROP'))->toThrow(InvalidArgumentException::class);
});
