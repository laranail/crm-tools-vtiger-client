<?php

declare(strict_types=1);

use Illuminate\Database\Query\Builder;
use Illuminate\Database\MySqlConnection;
use Illuminate\Database\Query\Grammars\MySqlGrammar;
use Illuminate\Database\Query\Processors\MySqlProcessor;
use Simtabi\Laranail\CrmTools\VtigerClient\Services\Operations;

/*
 * Operations::search() filled `?` bindings with the database driver's PDO::quote(). MySQL's quoter
 * escapes a quote with a backslash, which VTQL does not honour, and preg_replace() read `$1` or `\1`
 * in a binding as a back-reference. compileSearch() is the string half of search(), so it can be
 * checked without a CRM; no PDO is opened, because building SQL never needs one.
 */
function vtql_builder(): Builder
{
    $connection = new MySqlConnection(static fn () => throw new LogicException('no PDO needed'));

    return new Builder($connection, new MySqlGrammar($connection), new MySqlProcessor);
}

it('quotes bindings the way VTQL escapes them, without a database connection', function (): void {
    $query = vtql_builder()->from('Leads')->where('lastname', "O'Connor");

    expect(Operations::compileSearch($query))->toBe("select * from Leads where lastname = 'O''Connor';");
});

it('keeps an injection attempt inside one literal', function (): void {
    $query = vtql_builder()->from('Leads')->where('email', "x' OR '1'='1");

    expect(Operations::compileSearch($query))->toBe("select * from Leads where email = 'x'' OR ''1''=''1';");
});

it('inserts a binding containing $1 or \\1 verbatim', function (): void {
    $query = vtql_builder()->from('Leads')->where('firstname', 'a$1b')->where('lastname', 'c\\1d');

    expect(Operations::compileSearch($query))->toBe("select * from Leads where firstname = 'a$1b' and lastname = 'c\\1d';");
});

it('still moves an offset in front of the limit, as Vtiger requires', function (): void {
    $query = vtql_builder()->from('Leads')->where('lastname', 'Odhiambo')->limit(10)->offset(20);

    expect(Operations::compileSearch($query))->toBe("select * from Leads where lastname = 'Odhiambo' limit 20,10;");
});

it('leaves bindings unquoted only when asked', function (): void {
    $query = vtql_builder()->from('Leads')->where('createdtime', '2026-10-04');

    expect(Operations::compileSearch($query, false))->toBe('select * from Leads where createdtime = 2026-10-04;');
});

it('does not open the application database to quote a CRM query', function (): void {
    // A MySQL default connection nobody can reach: the old path called DB::connection()->getPdo().
    config([
        'database.default'                      => 'vtql-unreachable',
        'database.connections.vtql-unreachable' => ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 1, 'database' => 'x', 'username' => 'x', 'password' => ''],
    ]);

    $query = vtql_builder()->from('Leads')->where('lastname', "O'Connor");

    expect(Operations::compileSearch($query))->toBe("select * from Leads where lastname = 'O''Connor';");
});
