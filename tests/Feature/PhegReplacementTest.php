<?php

declare(strict_types=1);

use Simtabi\Laranail\CrmTools\VtigerClient\VtWsClient;
use Simtabi\Laranail\CrmTools\VtigerClient\Services\Session;
use Simtabi\Laranail\CrmTools\VtigerClient\Services\Entities;
use Simtabi\Laranail\CrmTools\VtigerClient\Support\Inflector;
use Simtabi\Laranail\CrmTools\VtigerClient\Support\ArrayQuery;
use Simtabi\Laranail\CrmTools\VtigerClient\Support\Transfigure;

/**
 * `simtabi/pheg` was required for three calls -- `str()->fromCamelCase()`,
 * `transfigure()->toObject()` and `arr()->query()` -- and is archived, and cannot be installed in
 * a working state (it imports `Simtabi\Enekia\...` without requiring it). These pin the in-package
 * replacements to the results pheg produced; the expected values were taken from pheg's own
 * implementation on the same inputs.
 */
it('converts camelCase the way pheg did', function (string $input, string $expected): void {
    expect(Inflector::fromCamelCase($input))->toBe($expected);
})->with([
    ['Accounts', 'accounts'],
    ['accounts', 'accounts'],
    ['ProductCategories', 'product_categories'],
    ['HelpDesk', 'help_desk'],
    ['SalesOrder', 'sales_order'],
    ['CRMEntity', 'crm_entity'],
    ['getHTTPResponse', 'get_http_response'],
    ['Module2Name', 'module2_name'],
    ['', ''],
]);

it('converts a nested array into nested stdClass objects', function (): void {
    $object = Transfigure::toObject([
        'expireTime' => 1700000000,
        'token'      => 'abc',
        'auth'       => ['sessionId' => 's-1', 'userId' => '19x1'],
        'empty'      => [],
    ]);

    expect($object)->toBeInstanceOf(stdClass::class)
        ->and($object->expireTime)->toBe(1700000000)
        ->and($object->token)->toBe('abc')
        ->and($object->auth)->toBeInstanceOf(stdClass::class)
        ->and($object->auth->sessionId)->toBe('s-1')
        ->and($object->auth->userId)->toBe('19x1')
        ->and($object->empty)->toEqual(new stdClass);
});

/**
 * The only parts of pheg's query engine this package called: `where($key, '!=', '')`,
 * `filter(callable)` and `toArray()`.
 */
it('filters records with where() keeping their keys, as pheg did', function (): void {
    $records = [
        ['id' => '11x1', 'email1' => 'a@example.com', 'accountstatus' => 'Active'],
        ['id' => '11x2', 'email1' => '',              'accountstatus' => 'Active'],
        ['id' => '11x3',                              'accountstatus' => 'Inactive'],
        ['id' => '11x4', 'email1' => 'd@example.com', 'accountstatus' => 'inactive'],
    ];

    expect((new ArrayQuery($records))->where('email1', '!=', '')->toArray())
        ->toBe([0 => $records[0], 3 => $records[3]]);
});

it('re-indexes after filter() and applies pending where() first, as pheg did', function (): void {
    $records = [
        ['id' => '11x1', 'email1' => 'a@example.com', 'accountstatus' => 'Active'],
        ['id' => '11x2', 'email1' => '',              'accountstatus' => 'Active'],
        ['id' => '11x3', 'email1' => 'c@example.com', 'accountstatus' => 'Inactive'],
        ['id' => '11x4', 'email1' => 'd@example.com', 'accountstatus' => 'active'],
    ];

    $result = (new ArrayQuery($records))
        ->where('email1', '!=', '')
        ->filter(fn (array $item): bool => strcasecmp($item['accountstatus'], 'active') === 0)
        ->toArray();

    expect($result)->toBe([$records[0], $records[3]]);
});

it('returns the records unchanged with no conditions, and treats two arguments as equality', function (): void {
    $records = [['id' => '1', 'n' => 5], ['id' => '2', 'n' => '5'], ['id' => '3', 'n' => 6]];

    expect((new ArrayQuery($records))->toArray())->toBe($records)
        ->and((new ArrayQuery($records))->where('n', 5)->toArray())->toBe([0 => $records[0], 1 => $records[1]])
        ->and((new ArrayQuery($records))->where('n', '>=', 6)->toArray())->toBe([2 => $records[2]])
        ->and((new ArrayQuery([]))->where('n', '!=', '')->toArray())->toBe([]);
});

it('refuses an operator it does not implement', function (): void {
    (new ArrayQuery([['a' => 1]]))->where('a', 'LIKE', '1')->toArray();
})->throws(InvalidArgumentException::class);

/**
 * `getNumericID()` is declared `int` and its body returns -1 when the ID has no `x` separator,
 * but on no match `getID()` returns null and `explode()` threw a TypeError under strict_types.
 */
it('returns -1 from getNumericID() when no record matches', function (): void {
    $client = Mockery::mock(VtWsClient::class);
    $client->shouldReceive('runQuery')->andReturn([]);

    $entities = new Entities($client, Mockery::mock(Session::class));

    expect($entities->getNumericID('Contacts', ['lastname' => 'Nobody']))->toBe(-1);
});

it('returns the numeric part from getNumericID() when a record matches', function (): void {
    $client = Mockery::mock(VtWsClient::class);
    $client->shouldReceive('runQuery')->andReturn([['id' => '12x345']]);

    $entities = new Entities($client, Mockery::mock(Session::class));

    expect($entities->getNumericID('Contacts', ['lastname' => 'Smith']))->toBe(345);
});

/**
 * Guard against the dependency coming back. Reads tokens rather than lines so the docblocks that
 * explain what replaced pheg do not count, and asserts how many files it read so a glob that stops
 * matching cannot pass vacuously.
 */
it('references neither simtabi/pheg nor simtabi/enekia from src/ or composer.json', function (): void {
    $root = dirname(__DIR__, 2);
    $files = new RegexIterator(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/src')), '/\.php$/');
    $inspected = 0;
    $offenders = [];

    foreach ($files as $file) {
        $inspected++;

        foreach (PhpToken::tokenize((string) file_get_contents((string) $file)) as $token) {
            if ($token->is([T_COMMENT, T_DOC_COMMENT])) {
                continue;
            }

            if (preg_match('/\bpheg\b|Simtabi\\\\(Pheg|Enekia)\b/i', $token->text) === 1) {
                $offenders[] = basename((string) $file) . ':' . $token->line . ' ' . $token->text;
            }
        }
    }

    $composer = (string) file_get_contents($root . '/composer.json');

    expect($inspected)->toBeGreaterThanOrEqual(15)
        ->and($offenders)->toBe([])
        ->and($composer)->not->toContain('simtabi/pheg')
        ->and($composer)->not->toContain('simtabi/enekia');
});
