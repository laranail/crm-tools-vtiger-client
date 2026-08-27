<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Archiver;

/**
 * Gzip-compressed tar archives. PharData reads `.tar.gz`/`.tgz` transparently,
 * so the hardened tar extraction (traversal/symlink/bomb guards) applies as-is.
 */
final class TarGz extends Tar {}
