<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Traits;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

trait FileProcessingTrait
{
    use FilePathGuard;

    /**
     * Get file contents.
     */
    public function getFile(string $filename, string $directory = 'uploads'): string
    {
        $this->assertSafePath($filename);
        $this->assertSafePath($directory);

        $filePath = $directory . '/' . $filename;

        if (Storage::exists($filePath)) {
            return (string) Storage::get($filePath);
        }

        return 'File not found';
    }

    /**
     * Upload a file.
     */
    public function uploadFile(UploadedFile $file, string $directory = 'uploads'): string
    {
        $this->assertSafePath($directory);

        // Use the original *basename* only so a malicious client-supplied
        // name with path segments cannot escape the target directory.
        $original = basename($file->getClientOriginalName());

        $filename = uniqid() . '_' . $original;

        $file->storeAs($directory, $filename);

        return $filename;
    }

    /**
     * Upload multiple files.
     *
     * @param array<int, UploadedFile> $files
     *
     * @return array<int, string>
     */
    public function uploadFiles(array $files, string $directory = 'uploads'): array
    {
        $filenames = [];

        foreach ($files as $file) {
            $filenames[] = $this->uploadFile($file, $directory);
        }

        return $filenames;
    }

    /**
     * Delete a file.
     */
    public function deleteFile(string $filename, string $directory = 'uploads'): void
    {
        $this->assertSafePath($filename);
        $this->assertSafePath($directory);

        Storage::delete($directory . '/' . $filename);
    }

    /**
     * Delete multiple files.
     *
     * @param array<int, string> $filenames
     */
    public function deleteFiles(array $filenames, string $directory = 'uploads'): void
    {
        foreach ($filenames as $filename) {
            $this->deleteFile($filename, $directory);
        }
    }
}
