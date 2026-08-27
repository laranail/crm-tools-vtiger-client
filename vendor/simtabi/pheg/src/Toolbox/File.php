<?php

namespace Simtabi\Pheg\Toolbox;

use ErrorException;
use Exception;
use FilesystemIterator;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use RuntimeException;
use Simtabi\Pheg\Core\Exceptions\PhegException;
use Simtabi\Pheg\Toolbox\JSON\JSON;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Mime\MimeTypes;

class File
{

    private Filesystem $filesystem;

    public function __construct()
    {
        $this->filesystem = new Filesystem();
    }

    /**
     * @return Filesystem
     */
    public function getFilesystem(): Filesystem
    {
        return $this->filesystem;
    }

    /**
     * Determine if a file or directory exists.
     *
     * @param  string  $path
     * @return bool
     */
    public function exists(string $path): bool
    {
        return file_exists($path);
    }

    /**
     * Determine if a file or directory is missing.
     *
     * @param  string  $path
     * @return bool
     */
    public function missing(string $path): bool
    {
        return ! $this->exists($path);
    }

    /**
     * Get the contents of a file.
     *
     * @param  string  $path
     * @param  bool  $lock
     *
     * @return string
     *
     * @throws PhegException
     */
    public function get(string $path, bool $lock = false): string
    {
        if ($this->isFile($path)) {
            return $lock ? $this->sharedGet($path) : file_get_contents($path);
        }

        throw new PhegException("File does not exist at path {$path}.");
    }

    /**
     * Get contents of a file with shared access.
     *
     * @param  string  $path
     * @return string
     */
    public function sharedGet(string $path): string
    {
        $contents = '';
        $handle   = fopen($path, 'rb');

        if ($handle) {
            try {
                if (flock($handle, LOCK_SH)) {
                    clearstatcache(true, $path);

                    $contents = fread($handle, $this->size($path) ?: 1);

                    flock($handle, LOCK_UN);
                }
            } finally {
                fclose($handle);
            }
        }

        return $contents;
    }

    /**
     * Get the returned value of a file.
     *
     * @param  string  $path
     * @param  array  $data
     * @return mixed
     *
     * @throws PhegException
     */
    public function getRequire(string $path, array $data = []): mixed
    {
        if ($this->isFile($path)) {
            $__path = $path;
            $__data = $data;

            return (static function () use ($__path, $__data) {
                extract($__data, EXTR_SKIP);

                return require $__path;
            })();
        }

        throw new PhegException("File does not exist at path {$path}.");
    }

    /**
     * Require the given file once.
     *
     * @param  string  $path
     * @param  array  $data
     * @return mixed
     *
     * @throws PhegException
     */
    public function requireOnce(string $path, array $data = []): mixed
    {
        if ($this->isFile($path)) {
            $__path = $path;
            $__data = $data;

            return (static function () use ($__path, $__data) {
                extract($__data, EXTR_SKIP);

                return require_once $__path;
            })();
        }

        throw new PhegException("File does not exist at path {$path}.");
    }

    /**
     * Get the MD5 hash of the file at the given path.
     *
     * @param  string  $path
     * @return string
     */
    public function hash(string $path): string
    {
        return md5_file($path);
    }

    /**
     * Write the contents of a file.
     *
     * @param  string  $path
     * @param  string  $contents
     * @param  bool  $lock
     * @return int|bool
     */
    public function put(string $path, string $contents, bool $lock = false): bool|int
    {
        return file_put_contents($path, $contents, $lock ? LOCK_EX : 0);
    }

    /**
     * Write the contents of a file, replacing it atomically if it already exists.
     *
     * @param string $path
     * @param string $content
     * @param bool   $overwrite
     *
     * @return bool
     * @throws PhegException
     */
    public function replace(string $path, string $content, bool $overwrite = false): bool
    {
        try {
            // If the path already exists and is a symlink, get the real path...
            clearstatcache(true, $path);

            $path = realpath($path) ?: $path;

            $tempPath = tempnam(dirname($path), basename($path));

            // Fix permissions of tempPath because `tempnam()` creates it with permissions set to 0600...
            chmod($tempPath, 0777 - umask());

            file_put_contents($tempPath, $content);

            $this->filesystem->rename($tempPath, $tempPath, $overwrite);
        }catch (IOException $exception) {
            throw new PhegException($exception->getMessage());
        }

        return false;
    }

    /**
     * Replace a given string within a given file.
     *
     * @param  array|string  $search
     * @param  array|string  $replace
     * @param  string  $path
     *
     * @return false|int
     */
    public function replaceInFile(string|array $search, string|array $replace, string $path): bool|int
    {
        return file_put_contents($path, str_replace($search, $replace, file_get_contents($path)));
    }

    /**
     * Prepend to a file.
     *
     * @param string $path
     * @param string $data
     *
     * @return bool|int
     * @throws PhegException
     */
    public function prepend(string $path, string $data): bool|int
    {
        if ($this->exists($path)) {
            return $this->put($path, $data.$this->get($path));
        }

        return $this->put($path, $data);
    }

    /**
     * Append to a file.
     *
     * @param  string  $path
     * @param  string  $data
     * @return int
     */
    public function append(string $path, string $data): int
    {
        return file_put_contents($path, $data, FILE_APPEND);
    }

    /**
     * Get or set UNIX mode of a file or directory.
     *
     * @param string   $path
     * @param int|null $mode
     * @param int      $offset
     *
     * @return string|bool
     */
    public function chmod(string $path, ?int $mode = null, int $offset = -4): string|bool
    {
        if ($mode) {
            return chmod($path, $mode);
        }

        return substr(sprintf('%o', fileperms($path)), $offset);
    }

    /**
     * Delete the file at a given path.
     *
     * @param string|array $paths
     *
     * @return bool
     * @throws PhegException
     */
    public function delete(string|array $paths): bool
    {
        try {
            $this->filesystem->remove($paths);

            return true;
        }catch (IOException $exception) {
            throw new PhegException($exception->getMessage());
        }
    }

    /**
     * Move a file to a new location.
     *
     * @param  string  $path
     * @param  string  $target
     * @return bool
     */
    public function move(string $path, string $target): bool
    {
        return rename($path, $target);
    }

    /**
     * Copies a file from source to a given destination
     *
     * @param string $originFile
     * @param string $targetFile
     * @param bool   $overwriteNewerFiles
     *
     * @return bool
     * @throws PhegException
     */
    public function copy(string $originFile, string $targetFile, bool $overwriteNewerFiles = false): bool
    {
        try {
            $this->filesystem->copy($originFile, $targetFile, $overwriteNewerFiles);
            return true;
        } catch (FileNotFoundException|IOException $exception) {
            throw new PhegException($exception->getMessage());
        }
    }

    /**
     * Create a symlink to the target file or directory. On Windows, a hard link is created if the target is a file.
     *
     * @param  string  $target
     * @param  string  $link
     *
     * @return bool
     */
    public function link(string $target, string $link): bool
    {
        if (! windows_os()) {
            return symlink($target, $link);
        }

        $mode = $this->isDirectory($target) ? 'J' : 'H';

        return exec("mklink /{$mode} ".escapeshellarg($link).' '.escapeshellarg($target));
    }

    /**
     * Create a relative symlink to the target file or directory.
     *
     * @param string $target
     * @param string $link
     *
     * @return void
     *
     * @throws PhegException
     */
    public function relativeLink(string $target, string $link): void
    {
        try {
            if (! class_exists(Filesystem::class)) {
                throw new RuntimeException(
                    'To enable support for relative links, please install the symfony/filesystem package.'
                );
            }

            $relativeTarget = (new Filesystem)->makePathRelative($target, dirname($link));

            $this->link($relativeTarget, $link);
        }catch (RuntimeException $exception) {
            throw new PhegException($exception->getMessage());
        }
    }

    /**
     * Extract the file name from a file path.
     *
     * @param  string  $path
     * @return string
     */
    public function name(string $path): string
    {
        return pathinfo($path, PATHINFO_FILENAME);
    }

    /**
     * Extract the trailing name component from a file path.
     *
     * @param  string  $path
     * @return string
     */
    public function basename(string $path): string
    {
        return pathinfo($path, PATHINFO_BASENAME);
    }

    /**
     * Extract the parent directory from a file path.
     *
     * @param  string  $path
     * @return string
     */
    public function dirname(string $path): string
    {
        return pathinfo($path, PATHINFO_DIRNAME);
    }

    /**
     * Extract the file extension from a file path.
     *
     * @param  string  $path
     * @return string
     */
    public function extension(string $path): string
    {
        return pathinfo($path, PATHINFO_EXTENSION);
    }

    /**
     * Guess the file extension from the mime-type of a given file.
     *
     * @param  string  $path
     *
     * @return string|null
     *
     * @throws PhegException
     */
    public function guessExtension(string $path): ?string
    {
        try {
            if (! class_exists(MimeTypes::class)) {
                throw new RuntimeException(
                    'To enable support for guessing extensions, please install the symfony/mime package.'
                );
            }

            return (new MimeTypes)->getExtensions($this->mimeType($path))[0] ?? null;
        }catch (RuntimeException $exception) {
            throw new PhegException($exception->getMessage());
        }
    }

    /**
     * Get the file type of given file.
     *
     * @param  string  $path
     * @return string
     */
    public function type(string $path): string
    {
        return filetype($path);
    }

    /**
     * Get the mime-type of a given file.
     *
     * @param  string  $path
     * @return string|false
     */
    public function mimeType(string $path): bool|string
    {
        return finfo_file(finfo_open(FILEINFO_MIME_TYPE), $path);
    }

    /**
     * Get the file size of a given file.
     *
     * @param  string  $path
     * @return int
     */
    public function size(string $path): int
    {
        return filesize($path);
    }

    /**
     * Get the file's last modification time.
     *
     * @param string $path
     *
     * @return int
     */
    public function lastModified(string $path): int
    {
        return filemtime($path);
    }

    /**
     * Determine if the given path is a directory.
     *
     * @param string $directory
     *
     * @return bool
     */
    public function isDirectory(string $directory): bool
    {
        return is_dir($directory);
    }

    /**
     * Determine if the given path is readable.
     *
     * @param string $path
     *
     * @return bool
     */
    public function isReadable(string $path): bool
    {
        return is_readable($path);
    }

    /**
     * Determine if the given path is writable.
     *
     * @param string $path
     *
     * @return bool
     */
    public function isWritable(string $path): bool
    {
        return is_writable($path);
    }

    /**
     * Determine if the given path is a file.
     *
     * @param string $file
     *
     * @return bool
     */
    public function isFile(string $file): bool
    {
        return is_file($file);
    }

    /**
     * Find path names matching a given pattern.
     *
     * @param string $pattern
     * @param int    $flags
     *
     * @return array
     */
    public function glob(string $pattern, int $flags = 0): array
    {
        return glob($pattern, $flags);
    }

    /**
     * Get an array of all files in a directory.
     *
     * @param  string  $directory
     * @param  bool  $hidden
     * @return SplFileInfo[]
     */
    public function files(string $directory, bool $hidden = false): array
    {
        return iterator_to_array(
            Finder::create()->files()->ignoreDotFiles(! $hidden)->in($directory)->depth(0)->sortByName(),
            false
        );
    }

    /**
     * Get all the files from the given directory (recursive).
     *
     * @param string $directory
     * @param bool   $hidden
     *
     * @return SplFileInfo[]
     */
    public function allFiles(string $directory, bool $hidden = false): array
    {
        return iterator_to_array(
            Finder::create()->files()->ignoreDotFiles(! $hidden)->in($directory)->sortByName(),
            false
        );
    }

    /**
     * Get all the directories within a given directory.
     *
     * @param string $directory
     *
     * @return array
     */
    public function directories(string $directory): array
    {
        $directories = [];

        foreach (Finder::create()->in($directory)->directories()->depth(0)->sortByName() as $dir) {
            $directories[] = $dir->getPathname();
        }

        return $directories;
    }

    /**
     * Ensure a directory exists.
     *
     * @param  string  $path
     * @param  int  $mode
     * @param  bool  $recursive
     *
     * @return bool
     */
    public function ensureDirectoryExists(string $path, int $mode = 0755, bool $recursive = true): bool
    {
        if (((! $this->isDirectory($path)) || (! $this->exists($path))) && (! $this->isFile($path))) {
            return $this->makeDirectory($path, $mode, $recursive);
        }

        return false;
    }

    /**
     * Create a directory.
     *
     * @param  string  $path
     * @param  int  $mode
     * @param  bool  $recursive
     * @param  bool  $force
     * @return bool
     */
    public function makeDirectory(string $path, int $mode = 0755, bool $recursive = false, bool $force = false): bool
    {
        if ($force) {
            return @mkdir($path, $mode, $recursive);
        }

        return mkdir($path, $mode, $recursive);
    }

    /**
     * Move a directory.
     *
     * @param string $from
     * @param string $to
     * @param bool   $overwrite
     *
     * @return bool
     * @throws PhegException
     */
    public function moveDirectory(string $from, string $to, bool $overwrite = false): bool
    {
        if ($overwrite && $this->isDirectory($to) && ! $this->deleteDirectory($to)) {
            return false;
        }

        return @rename($from, $to) === true;
    }

    /**
     * Copy a directory from one location to another.
     *
     * @param string   $directory
     * @param string   $destination
     * @param int|null $options
     *
     * @return bool
     * @throws PhegException
     */
    public function copyDirectory(string $directory, string $destination, int $options = null): bool
    {
        if (! $this->isDirectory($directory)) {
            return false;
        }

        $options = $options ?: FilesystemIterator::SKIP_DOTS;

        // If the destination directory does not actually exist, we will go ahead and
        // create it recursively, which just gets the destination prepared to copy
        // the files over. Once we make the directory we'll proceed the copying.
        $this->ensureDirectoryExists($destination, 0777);

        $items = new FilesystemIterator($directory, $options);

        foreach ($items as $item) {
            // As we spin through items, we will check to see if the current file is actually
            // a directory or a file. When it is actually a directory we will need to call
            // back into this function recursively to keep copying these nested folders.
            $target = $destination.'/'.$item->getBasename();

            if ($item->isDir()) {
                $path = $item->getPathname();

                if (! $this->copyDirectory($path, $target, $options)) {
                    return false;
                }
            }

            // If the current items is just a regular file, we will just copy this to the new
            // location and keep looping. If for some reason the copy fails we'll bail out
            // and return false, so the developer is aware that the copy process failed.
            else {
                if (! $this->copy($item->getPathname(), $target)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Recursively delete a directory.
     *
     * The directory itself may be optionally preserved.
     *
     * @param string $directory
     * @param bool   $preserve
     *
     * @return bool
     * @throws PhegException
     */
    public function deleteDirectory(string $directory, bool $preserve = false)
    {
        if (! $this->isDirectory($directory)) {
            return false;
        }

        $items = new FilesystemIterator($directory);

        foreach ($items as $item) {
            // If the item is a directory, we can just recurse into the function and
            // delete that sub-directory otherwise we'll just delete the file and
            // keep iterating through each file until the directory is cleaned.
            if ($item->isDir() && ! $item->isLink()) {
                $this->deleteDirectory($item->getPathname());
            }

            // If the item is just a file, we can go ahead and delete it since we're
            // just looping through and waxing all of the files in this directory
            // and calling directories recursively, so we delete the real path.
            else {
                $this->delete($item->getPathname());
            }
        }

        if (! $preserve) {
            @rmdir($directory);
        }

        return true;
    }

    /**
     * Remove all the directories within a given directory.
     *
     * @param string $directory
     *
     * @return bool
     * @throws PhegException
     */
    public function deleteDirectories(string $directory): bool
    {
        $allDirectories = $this->directories($directory);

        if (! empty($allDirectories)) {
            foreach ($allDirectories as $directoryName) {
                $this->deleteDirectory($directoryName);
            }

            return true;
        }

        return false;
    }

    /**
     * Empty the specified directory of all files and folders.
     *
     * @param string $directory
     *
     * @return bool
     * @throws PhegException
     */
    public function cleanDirectory(string $directory): bool
    {
        return $this->deleteDirectory($directory, true);
    }

    /**
     * Appends timestamp to a given file.
     *
     * @param string $sourceFileName
     * @param bool $isActualFilePath :true if you are renaming an actual file in a given path
     * @return bool|string
     */
    public function appendTimestampToFileName(string $sourceFileName, bool $isActualFilePath = false): bool|string
    {
        $oldFileName = basename($sourceFileName);
        $newFileName = str_replace($oldFileName, date('Y_m_d_his_').$oldFileName, $sourceFileName);

        return $isActualFilePath ? $this->rename($oldFileName, $newFileName) : $newFileName;
    }

    /**
     * Appends content into a given file
     *
     * @param string $sourceFilePath
     * @param string $content
     * @return bool
     */
    public function appendContentToFile(string $sourceFilePath, string $content): bool
    {
        if ($this->filesystem->exists($sourceFilePath)) {
            $this->filesystem->appendToFile($sourceFilePath, $content);
            return true;
        }

        return false;
    }

    /**
     * Renames a file
     *
     * @param string $oldFileNamePath
     * @param string $newFileNamePath
     * @return bool
     */
    public function rename(string $oldFileNamePath, string $newFileNamePath): bool
    {
        if ($this->filesystem->exists($oldFileNamePath)) {
            $this->filesystem->rename($oldFileNamePath, $newFileNamePath, true);
            return true;
        }

        return false;
    }

    /**
     * Fill placeholders in given single file.
     *
     * @param string $filePath     The file with the generic placeholders in it
     * @param array  $placeholders Array containing placeholder in a key to value notation
     *
     * @return bool|int
     * @throws PhegException
     */
    public function fillPlaceholdersInFile(string $filePath, array $placeholders): bool|int
    {
        try {
            return $this->put($filePath, str_replace(
                array_keys($placeholders),
                array_values($placeholders),
                $this->get($filePath)
            ));
        }catch (FileNotFoundException $exception) {
            throw new PhegException($exception->getMessage());
        }
    }

    /**
     * Gets file permissions
     *
     * @param string $path
     * @param int    $offset
     *
     * @return string|null
     * @throws PhegException
     */
    public function getPerms(string $path, int $offset = -4): ?string
    {

        try {
            if ($this->isReadable($path) || $this->exists($path) || $this->isWritable($path) || $this->isFile($path)) {
                clearstatcache();
                return substr(sprintf('%o', fileperms($path)), $offset);
            }else {
                throw new PhegException(sprintf('The given path "%s" does not exist', $path));
            }
        }catch (ErrorException|PhegException $exception) {
            throw new PhegException($exception->getMessage());
        }

    }

    /**
     * Get the size of a given directory
     *
     * @param string $path
     *
     * @return int
     */
    public function getDirectorySize(string $path): int
    {
        $size = 0;
        foreach ($this->glob(rtrim($path, '/') . '/*', GLOB_NOSORT) as $each) {
            $size += $this->isFile($each) ? $this->size($each) : $this->getDirectorySize($each);
        }

        return $size;
    }

    /**
     * Fetch all contents of a file
     *
     * @param string $file
     * @param bool   $convertToArray
     *
     * @return array|mixed|string|null
     * @throws PhegException
     */
    public function getFileData(string $file, bool $convertToArray = true): mixed
    {
        $file = $this->get($file);
        if (! empty($file)) {
            if ($convertToArray) {
                return json_decode($file, true);
            }

            return $file;
        }

        if (! $convertToArray) {
            return null;
        }

        return [];
    }

    /**
     * Saves data to a file
     *
     * @param string       $path
     * @param array|string $data
     * @param bool         $json
     *
     * @return bool
     */
    public function saveFileData(string $path, array|string $data, bool $json = true): bool
    {
        try {
            if ($json) {
                $data = (new JSON())->encodePrettify($data);
            }

            if (! $this->isDirectory($this->dirname($path))) {
                $this->makeDirectory($this->dirname($path), 493, true);
            }

            $this->put($path, $data);

            return true;
        } catch (Exception $exception) {
            info($exception->getMessage());

            return false;
        }
    }

    /**
     * Get all files in a given directory
     *
     * @param string $path
     * @param array  $ignoreFiles
     *
     * @return array
     */
    public function scanDirectory(string $path, array $ignoreFiles = []): array
    {
        if ($this->isDirectory($path)) {
            $data = array_diff(scandir($path), array_merge(['.', '..', '.DS_Store'], $ignoreFiles));
            natsort($data);

            return $data;
        }

        return [];
    }

    /**
     * Load helpers from a directory
     *
     * @param string $directory
     *
     * @throws PhegException
     * @since 2.0
     */
    public function autoload(string $directory): void
    {
        foreach ($this->glob($directory . '/*.php') as $helper) {
            $this->requireOnce($helper);
        }
    }

    /**
     * Unlink storage symbolic link
     *
     * @return bool
     */
    public function deleteStorageSymlink(): bool
    {
        try {

            $path = public_path('storage');
            if ($this->exists($path)) {
                if ($this->delete($path)) {
                    return true;
                }
            }else{
                return true;
            }

        } catch (Exception $exception) {
            info($exception->getMessage());
        }
        return false;
    }
}
