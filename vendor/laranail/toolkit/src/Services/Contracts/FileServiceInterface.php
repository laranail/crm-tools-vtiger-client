<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Services\Contracts;

use Simtabi\Laranail\Toolkit\Traits\FilePathGuard;

/**
 * File-name / size inspection plus thin, path-guarded filesystem probes.
 *
 * The string helpers (extension, filenameWithoutExtension, isImage,
 * sanitizeFilename, formatFileSize) touch no filesystem. The probes (exists,
 * size, lastModified, fileInfo, toDataUri, fromJson) are exception-safe,
 * read-only wrappers over the `File` facade, each guarded against `..`/null-byte
 * paths via the canonical {@see FilePathGuard}.
 *
 * Resolve it from the container (bound as a singleton) or inject it by type —
 * `app(FileServiceInterface::class)` / constructor injection. I/O that merely
 * passes through to Storage/File — read/write/copy/move/delete — is deliberately
 * NOT folded; call those facades directly.
 */
interface FileServiceInterface
{
    /** Human-readable byte size, e.g. 1024 → "1 KB". */
    public function formatFileSize(int $bytes, int $precision = 2): string;

    /** The lower-cased extension of a path, without the dot. */
    public function extension(string $path): string;

    /** The basename of a path without its extension. */
    public function filenameWithoutExtension(string $path): string;

    /** Whether the path has a common image extension. */
    public function isImage(string $path): bool;

    /**
     * Strip path separators, null bytes and unsafe characters from a file NAME
     * (not a path). Apply after basename(), before storing an uploaded name.
     */
    public function sanitizeFilename(string $filename): string;

    /**
     * Whether a file exists at the given path.
     *
     * Read-only, exception-safe wrapper over the `File` facade. Returns false
     * for unsafe paths (`..` traversal segments or null bytes) without throwing.
     */
    public function exists(string $path): bool;

    /**
     * The size of a file in bytes, or 0 when it is missing or the path is unsafe.
     */
    public function size(string $path): int;

    /** The file's last-modified UNIX timestamp, or 0 when missing/unsafe. */
    public function lastModified(string $path): int;

    /**
     * Whether the path's (lower-cased) extension is in the allowed list.
     *
     * @param list<string> $allowed
     */
    public function hasAllowedExtension(string $path, array $allowed): bool;

    /**
     * Whether a file exists and is no larger than $maxMb megabytes. Path-guarded;
     * a non-positive $maxMb means "no upper bound" (existence only).
     */
    public function validateSize(string $path, int $maxMb): bool;

    /**
     * Combined existence + extension + optional size validation. Returns false
     * for an unsafe/missing path, a disallowed extension, or an oversize file.
     *
     * @param list<string> $allowedExtensions
     */
    public function validate(string $path, array $allowedExtensions, ?int $maxMb = null): bool;

    /**
     * Inspect a file, returning its path/size/extension/name metadata.
     *
     * @return array{path: string, size: int, extension: string, name: string, basename: string, last_modified: int, is_readable: bool, is_writable: bool}|array{}
     */
    public function fileInfo(string $path): array;

    /**
     * Generate a random file name with the given extension, e.g.
     * generateName('pdf') → "Xa9...q2.pdf". The leading dot on the extension is
     * optional.
     */
    public function generateName(string $extension, int $length = 25): string;

    /**
     * Encode an existing file as a base64 `data:` URI
     * ("data:<mime>;base64,<payload>"), or an empty string when the file is
     * missing or the path is unsafe.
     */
    /**
     * Relative paths of every file under a directory. Non-recursive by default.
     *
     * @return list<string>
     */
    public function filesInPath(string $directory, bool $recursive = false): array;

    public function toDataUri(string $path): string;

    /**
     * Read JSON from a file path (when one exists at $pathOrContent) or treat the
     * argument as a raw JSON string, decoding to an associative array. Returns
     * null on a missing/unsafe path or invalid JSON.
     *
     * @return array<int|string, mixed>|null
     */
    public function fromJson(string $pathOrContent): ?array;
}
