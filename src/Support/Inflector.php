<?php

declare(strict_types=1);

namespace Simtabi\Laranail\CrmTools\VtigerClient\Support;

/**
 * Word-case conversion for module names.
 *
 * Replaces `simtabi/pheg`'s `Str::fromCamelCase()`, which this package used to map a Vtiger module
 * name (`ProductCategories`) onto a fetcher key (`product_categories`). The pattern and the
 * per-word rule are pheg's, verbatim, so results are unchanged: an all-capitals run is lowercased
 * (`CRMEntity` -> `crm_entity`), any other word is lcfirst-ed.
 */
final class Inflector
{
    private const string WORD = '!([A-Z][A-Z0-9]*(?=$|[A-Z][a-z0-9])|[A-Za-z][a-z0-9]+)!';

    public static function fromCamelCase(string $value): string
    {
        preg_match_all(self::WORD, $value, $matches);

        $words = array_map(
            static fn (string $word): string => $word === strtoupper($word) ? strtolower($word) : lcfirst($word),
            $matches[0],
        );

        return implode('_', $words);
    }
}
