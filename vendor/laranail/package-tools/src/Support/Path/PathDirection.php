<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Support\Path;

/**
 * Which way PathResolver walks from the calling file's directory.
 *
 * There is no default. A caller that omits it is asking for a path without saying which direction,
 * and guessing there is how `__DIR__ . '/../../config'` becomes `__DIR__ . '/../config'` in a move
 * and nobody notices until a require fails in production.
 */
enum PathDirection
{
    /** Climb toward the filesystem root -- the package root, from a file inside src/. */
    case Outer;

    /** Descend into the calling file's own directory tree. */
    case Inner;
}
