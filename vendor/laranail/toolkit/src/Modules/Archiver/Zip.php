<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Archiver;

use ZipArchive;

final class Zip extends Extractor
{
    /**
     * The Unix mode bits ZIP stores in the high 16 of `external_attributes`,
     * and the file-type mask that identifies a symbolic link.
     */
    private const int UNIX_MODE_SHIFT = 16;

    private const int S_IFMT = 0o170000;

    private const int S_IFLNK = 0o120000;

    /**
     * ## On the declared sizes this guard trusts
     *
     * `statIndex()['size']` is the archive's own account of itself, which
     * invites the obvious objection: an attacker writes that field, so summing
     * it should be worthless.
     *
     * Measured, it is not. libzip **truncates extraction to the declared
     * size** — an archive claiming `size => 1` behind a 64 KB payload yields a
     * 1-byte file, through both `extractTo()` and `getStream()`. Understating
     * the size therefore limits the extraction rather than enabling it, and the
     * real zip bomb — a small compressed payload with an honestly declared huge
     * uncompressed size — is exactly what summing the declarations catches.
     *
     * This is recorded because an earlier pass read the code, reasoned that
     * trusting attacker-controlled metadata must be exploitable, and set out to
     * replace `extractTo()` with hand-rolled streaming to "fix" it. The
     * reasoning was sound and the conclusion was wrong; the probe is in
     * `ZipBombGuardTest`.
     */
    public function extract(string $pathToArchive, string $pathToDirectory): void
    {
        $archive = new ZipArchive();

        if ($archive->open($pathToArchive) !== true) {
            throw ArchiveException::cannotOpen($pathToArchive);
        }

        $this->ensureDestination($pathToDirectory);

        // Validate EVERY entry before writing anything (fail-closed Zip-Slip guard).
        $total = 0;
        for ($i = 0; $i < $archive->numFiles; $i++) {
            $stat = $archive->statIndex($i);

            if ($stat === false) {
                $archive->close();

                throw ArchiveException::cannotOpen($pathToArchive);
            }

            $name = (string) $stat['name'];

            $this->assertWithinDestination($pathToDirectory, $name);
            $this->assertNotSymlink($archive, $i, $name);

            $total += (int) $stat['size'];
            $this->assertWithinLimits($i + 1, $total);
        }

        if (!$archive->extractTo($pathToDirectory)) {
            $archive->close();

            throw ArchiveException::cannotOpen($pathToArchive);
        }

        $archive->close();
    }

    /**
     * Refuse an entry marked as a symbolic link.
     *
     * The tar path has always done this (`Extractor::validatePharEntries()`
     * checks `isLink()`); the ZIP path validated names only, while three
     * docblocks in this module claimed symlink safety for both. This makes the
     * claim true.
     *
     * PHP's current `extractTo()` materialises such an entry as a regular file
     * containing the link target rather than as a link, so this is not today's
     * traversal vector — `assertWithinDestination()` is. But that is a property
     * of the libzip build, not of the format, and the docs should not be
     * describing a guarantee that rests on it.
     */
    private function assertNotSymlink(ZipArchive $archive, int $index, string $name): void
    {
        $read = $archive->getExternalAttributesIndex($index, $opsys, $attr);

        if ($read !== true || $opsys !== ZipArchive::OPSYS_UNIX) {
            return;
        }

        $mode = ((int) $attr) >> self::UNIX_MODE_SHIFT;

        if (($mode & self::S_IFMT) === self::S_IFLNK) {
            throw ArchiveException::unsafeEntry($name);
        }
    }
}
