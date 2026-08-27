<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Helpers;

use Simtabi\Laranail\Toolkit\Helpers\Concerns\InteractsWithArrays;
use Simtabi\Laranail\Toolkit\Helpers\Concerns\InteractsWithConsole;
use Simtabi\Laranail\Toolkit\Helpers\Concerns\InteractsWithDates;
use Simtabi\Laranail\Toolkit\Helpers\Concerns\InteractsWithStrings;
use Simtabi\Laranail\Toolkit\Services\Contracts\FileServiceInterface;
use Simtabi\Laranail\Toolkit\Services\Contracts\SystemServiceInterface;

/**
 * One **stateless** static helper facade for the toolkit's PURE-FUNCTION
 * domains, composed of per-domain traits.
 *
 * Every method here is `static` and side-effect free — call them directly
 * (`Helper::ucWords()`), no container resolution or injection required. The
 * STATEFUL / swappable domains that used to live here (files, system) are now
 * injectable, interface-backed services and are NO LONGER on this class:
 *
 * - File domain   → {@see FileServiceInterface}
 *                   (`app(FileServiceInterface::class)` / `Toolkit::file()`)
 * - System domain → {@see SystemServiceInterface}
 *                   (`app(SystemServiceInterface::class)` / `Toolkit::system()`)
 *
 * Database tooling (connection probes, UUID model traits, schema macros, the
 * `db` console command) lives in the dedicated `laranail/db-tools` package.
 *
 * What remains here (genuinely pure, regrouped by concern into the {@see Concerns}
 * `InteractsWith*` traits):
 *
 * - {@see InteractsWithArrays}  — arrayTrim, arrayFlatten, arrayToDotNotation
 * - {@see InteractsWithStrings} — strBetween, strSlugify, ucWords, usernameFromEmail,
 *                                 emailFromUsername, nameToUsernames, generateUsername,
 *                                 escapeHtml, classBasename, randomIntExcept,
 *                                 faker, interpolate, stripTags, linesCount
 * - {@see InteractsWithDates}   — carbonParse, carbonHumanDiff
 * - {@see InteractsWithConsole} — write
 *
 * These are deliberately **not** fronted by the `Toolkit` facade — they are pure
 * static utilities, so call them by class directly.
 */
final class Helper
{
    use InteractsWithArrays;
    use InteractsWithConsole;
    use InteractsWithDates;
    use InteractsWithStrings;
}
