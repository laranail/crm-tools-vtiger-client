<?php

declare(strict_types=1);

/**
 * Regenerates src/Enums/Timezone.php from the current tzdata.
 *
 *   php tools/generate-timezone-enum.php            write the file
 *   php tools/generate-timezone-enum.php --check    exit 1 if the file on disk differs
 *
 * `--check` exists because the parity test in tests/Unit/Enums answers whether the enum's
 * *content* still matches tzdata, which is the question that matters for correctness, but not
 * whether the file is still a function of the generator. A hand edit that adds a method, or a
 * reformat, leaves parity intact and makes the next regeneration a surprising diff.
 *
 * Note this enum is deprecated in favour of `laranail/chrono`'s — see the class docblock. It is
 * kept generated rather than frozen so the two cannot diverge while both exist. chrono cannot be
 * depended on from here: chrono already depends on this package, and that is a cycle.
 */
$check = in_array('--check', $argv, true);
$target = __DIR__ . '/../src/Enums/Timezone.php';

$identifiers = DateTimeZone::listIdentifiers();

$caseName = static function (string $identifier): string {
    $name = str_replace(['+', '-'], [' Plus ', ' Minus '], $identifier);
    $name = str_replace(['/', '_'], ' ', $name);

    return str_replace(' ', '', ucwords(strtolower($name)));
};

$cases = '';
$seen = [];
foreach ($identifiers as $identifier) {
    $name = $caseName($identifier);

    // two identifiers normalizing to one case name would be a fatal enum
    // redeclaration in the generated file — refuse to write it
    if (isset($seen[$name])) {
        fwrite(STDERR, "case-name collision: '{$seen[$name]}' and '{$identifier}' both normalize to '{$name}'\n");
        exit(1);
    }

    $seen[$name] = $identifier;
    $cases .= sprintf("    case %s = '%s';\n", $name, $identifier);
}

$code = <<<PHP
<?php

declare(strict_types=1);

namespace Simtabi\\Laranail\\Package\\Tools\\Enums;

use DateTimeZone;

/**
 * every iana timezone identifier php knows, as a typed case. GENERATED —
 * never edit by hand; regenerate with:
 *
 *   php tools/generate-timezone-enum.php
 *
 * @deprecated Use `Simtabi\\Laranail\\Chrono\\Core\\Enums\\Timezone`, which has identical case
 *             names and identical values, plus city()/kind()/canonical() and companion enums for
 *             the legacy identifiers and abbreviations. A timezone enum has nothing to do with
 *             building Laravel packages; chrono is where it lives beside the alias map and the
 *             resolver that keep it honest.
 *
 *             Migration is a one-line `use` change per file — see chrono's
 *             docs/recipes/migrate-off-the-package-tools-enum.md.
 *
 *             This copy stays generated, and gated by tools/generate-timezone-enum.php --check,
 *             so it cannot drift from tzdata while it still exists. It is not re-exported from
 *             chrono: chrono depends on this package, so depending back would be a cycle.
 */
enum Timezone: string
{
{$cases}
    public function toDateTimeZone(): DateTimeZone
    {
        return new DateTimeZone(\$this->value);
    }
}

PHP;

if ($check) {
    $current = is_file($target) ? (string) file_get_contents($target) : '';

    if ($current === $code) {
        fwrite(STDOUT, sprintf("Timezone enum is in sync (%d identifiers, tzdata %s).\n", count($identifiers), timezone_version_get()));

        exit(0);
    }

    fwrite(STDERR, sprintf(
        "src/Enums/Timezone.php does not match what tools/generate-timezone-enum.php produces\n"
        . "on this host (tzdata %s).\n\n"
        . "Either the file was edited by hand, or this host carries a different tzdata release\n"
        . "than the one it was generated against. Run the generator and read the diff before\n"
        . "committing it — a changed case list is a real tzdata move; a changed docblock or\n"
        . "spacing is a hand edit being reverted.\n",
        timezone_version_get(),
    ));

    exit(1);
}

file_put_contents($target, $code);
echo count($identifiers) . " identifiers written\n";
