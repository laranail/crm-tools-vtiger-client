<?php

declare(strict_types=1);

use Simtabi\Laranail\CrmTools\VtigerClient\Support\ArrayQuery;

/*
 * Operations::fetchDeepWithPagination() returned simtabi/pheg's QueryEngine. ArrayQuery replaces
 * it, so callers of that result keep working. Every expected value below is what pheg's own
 * QueryEngine returned for the same records, captured by running both side by side.
 */
function parity_records(): array
{
    return [
        ['id' => '4x1', 'lastname' => 'Odhiambo', 'email1' => 'amina@example.com', 'annual' => '120', 'status' => 'Hot', 'address' => ['city' => 'Nairobi']],
        ['id' => '4x2', 'lastname' => 'banda', 'email1' => '', 'annual' => '80', 'status' => 'Warm', 'address' => ['city' => 'Lusaka']],
        ['id' => '4x3', 'lastname' => 'Carter', 'email1' => 'c@x.org', 'annual' => '200', 'status' => 'Hot', 'address' => ['city' => 'Nairobi']],
        ['id' => '4x4', 'lastname' => 'adams', 'annual' => '50', 'status' => 'Cold', 'address' => ['city' => 'Accra']],
    ];
}

it('ORs where-groups and keeps keys, as pheg did', function (): void {
    $kept = (new ArrayQuery(parity_records()))->where('status', '=', 'Cold')->orWhere('lastname', '=', 'Carter')->toArray();

    expect(array_keys($kept))->toBe([2, 3]);
});

it('filters with whereIn, whereNotIn, whereNull and a dotted key', function (): void {
    $q = fn () => new ArrayQuery(parity_records());

    expect(array_keys($q()->whereIn('status', ['Hot', 'Cold'])->toArray()))->toBe([0, 2, 3])
        ->and(array_keys($q()->whereNotIn('status', ['Hot'])->toArray()))->toBe([1, 3])
        ->and(array_keys($q()->whereNull('email1')->toArray()))->toBe([3])
        ->and(array_keys($q()->where('address.city', 'Nairobi')->toArray()))->toBe([0, 2]);
});

it('aggregates numeric strings the way pheg did', function (): void {
    $q = fn () => new ArrayQuery(parity_records());

    expect($q()->where('status', 'Hot')->count())->toBe(2)
        ->and($q()->sum('annual'))->toBe(450)
        ->and($q()->avg('annual'))->toBe(112.5)
        ->and($q()->where('status', 'Nope')->exists())->toBeFalse();
});

it('sorts case-insensitively and numerically', function (): void {
    expect(array_column((new ArrayQuery(parity_records()))->sortBy('lastname')->toArray(), 'lastname'))
        ->toBe(['adams', 'banda', 'Carter', 'Odhiambo'])
        ->and(array_column((new ArrayQuery(parity_records()))->sortBy('annual', 'desc')->toArray(), 'annual'))
        ->toBe(['200', '120', '80', '50']);
});

it('wraps single results in an ArrayQuery readable like pheg', function (): void {
    $first = (new ArrayQuery(parity_records()))->where('status', 'Hot')->first();

    expect($first)->toBeInstanceOf(ArrayQuery::class)
        ->and($first['id'])->toBe('4x1')
        ->and($first->lastname)->toBe('Odhiambo')
        ->and((new ArrayQuery(parity_records()))->last()['id'])->toBe('4x4')
        ->and((new ArrayQuery(parity_records()))->nth(2)['id'])->toBe('4x2')
        ->and((new ArrayQuery(parity_records()))->nth(9))->toBeNull()
        ->and((new ArrayQuery(parity_records()))->column('lastname')->toArray())->toBe(['Odhiambo', 'banda', 'Carter', 'adams']);
});

it('groups, de-duplicates and pages like pheg', function (): void {
    expect(array_keys((new ArrayQuery(parity_records()))->groupBy('status')->toArray()))->toBe(['Hot', 'Warm', 'Cold'])
        ->and(array_column((new ArrayQuery(parity_records()))->distinct('status')->toArray(), 'id'))->toBe(['4x1', '4x2', '4x4'])
        ->and(array_column((new ArrayQuery(parity_records()))->offset(1)->take(2)->get()->toArray(), 'id'))->toBe(['4x2', '4x3']);
});

it('is countable, iterable, JSON-serialisable and array-accessible', function (): void {
    $q = new ArrayQuery(parity_records());

    expect(count($q))->toBe(4)
        ->and(iterator_to_array($q))->toHaveCount(4)
        ->and(json_decode(json_encode($q), true))->toHaveCount(4)
        ->and(isset($q[3]))->toBeTrue()
        ->and($q[9])->toBeNull();
});

it('returns the selected columns from get(), where pheg ignored them', function (): void {
    expect((new ArrayQuery(parity_records()))->get(['id', 'lastname'])->first()->toArray())
        ->toBe(['id' => '4x1', 'lastname' => 'Odhiambo']);
});

it('treats a missing key as no match in the string clauses, where pheg threw', function (): void {
    expect(array_keys((new ArrayQuery(parity_records()))->whereContains('email1', 'example')->toArray()))->toBe([0]);
});

it('still rejects an operator it does not know', function (): void {
    (new ArrayQuery(parity_records()))->where('status', 'between', 'x');
})->throws(InvalidArgumentException::class);
