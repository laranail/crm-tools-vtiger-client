# Archiver module

Create and safely extract `tar`, `tar.gz`, and `zip` archives behind
`ArchiverServiceInterface`. Bound through a deferred provider (alias
`laranail.archiver`, facade `Archiver`). The zip extractor requires `ext-zip`.

```php
use Simtabi\Laranail\Toolkit\Modules\Archiver\ArchiverServiceInterface;
use Simtabi\Laranail\Toolkit\Modules\Archiver\Archiver;
```

## Extract

Pick the extractor automatically from the file extension:

```php
Archiver::extract(
    storage_path('app/release.zip'),
    storage_path('app/release'),
);
```

Or via the contract:

```php
app(ArchiverServiceInterface::class)->extract($pathToArchive, $pathToDirectory);
```

## Per-format access

```php
Archiver::zip();    // Zip service
Archiver::tar();    // Tar service
Archiver::tarGz();  // TarGz service
```

## Security: Zip-Slip hardened

Extraction is **fail-closed**. Before any bytes are written, every archive entry
is validated:

- **Path traversal / Zip-Slip** — each entry's destination is asserted to stay
  within the target directory; entries that would escape (e.g. `../../etc`) abort
  the whole extraction with an `ArchiveException`.
- **Symlinks** — symlinked entries are rejected.
- **Zip bombs** — total file count and uncompressed size are checked against
  limits before extraction proceeds. The defaults are **10,000 entries** and
  **1 GiB** uncompressed; adjust them on the `Extractor` with
  `setLimits(int $maxEntries, int $maxTotalBytes): static` before extracting.

A malformed or unreadable entry aborts the operation rather than partially
extracting. Always extract untrusted archives into a dedicated, isolated
directory.

[← Docs index](../../README.md#documentation)
