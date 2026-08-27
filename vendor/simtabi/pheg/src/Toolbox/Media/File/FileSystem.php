<?php declare(strict_types=1);

namespace Simtabi\Pheg\Toolbox\Media\File;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Exception;
use Simtabi\Pheg\Toolbox\Transfigures\Transfigure;
use SplFileInfo;
use Simtabi\Pheg\Toolbox\System;
use DirectoryIterator;

/**
 * Class FileSystem
 *
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 * @SuppressWarnings(PHPMD.ShortClassName)
 */
final class FileSystem
{
    public const TYPE_SOCKET            = 0xC000;
    public const TYPE_SYMLINK           = 0xA000;
    public const TYPE_REGULAR           = 0x8000;
    public const TYPE_BLOCK             = 0x6000;
    public const TYPE_DIR               = 0x4000;
    public const TYPE_CHARACTER         = 0x2000;
    public const TYPE_FIFO              = 0x1000;

    public const PERM_OWNER_READ        = 0x0100;
    public const PERM_OWNER_WRITE       = 0x0080;
    public const PERM_OWNER_EXEC        = 0x0040;
    public const PERM_OWNER_EXEC_STICKY = 0x0800;

    public const PERM_GROUP_READ        = 0x0020;
    public const PERM_GROUP_WRITE       = 0x0010;
    public const PERM_GROUP_EXEC        = 0x0008;
    public const PERM_GROUP_EXEC_STICKY = 0x0400;

    public const PERM_ALL_READ          = 0x0004;
    public const PERM_ALL_WRITE         = 0x0002;
    public const PERM_ALL_EXEC          = 0x0001;
    public const PERM_ALL_EXEC_STICKY   = 0x0200;

    public function __construct() {}

    /**
     * Returns the file permissions as a nice string, like -rw-r--r-- or false if the file is not found.
     *
     * @param string   $file  The name of the file to get permissions form
     * @param int|null $perms Numerical value of permissions to display as text.
     * @return string
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function perms(string $file, int $perms = null): string
    {
        if (null === $perms) {
            if (!file_exists($file)) {
                return '';
            }

            /** @noinspection CallableParameterUseCaseInTypeContextInspection */
            $perms = fileperms($file);
        }

        // @codeCoverageIgnoreStart
        $info = 'u'; // undefined
        if (($perms & self::TYPE_SOCKET) === self::TYPE_SOCKET) {
            $info = 's';
        } elseif (($perms & self::TYPE_SYMLINK) === self::TYPE_SYMLINK) {
            $info = 'l';
        } elseif (($perms & self::TYPE_REGULAR) === self::TYPE_REGULAR) {
            $info = '-';
        } elseif (($perms & self::TYPE_BLOCK) === self::TYPE_BLOCK) {
            $info = 'b';
        } elseif (($perms & self::TYPE_DIR) === self::TYPE_DIR) {
            $info = 'd';
        } elseif (($perms & self::TYPE_CHARACTER) === self::TYPE_CHARACTER) {
            $info = 'c';
        } elseif (($perms & self::TYPE_FIFO) === self::TYPE_FIFO) {
            $info = 'p';
        }
        // @codeCoverageIgnoreEnd

        // Owner
        $info .= (($perms & self::PERM_OWNER_READ) ? 'r' : '-');
        $info .= (($perms & self::PERM_OWNER_WRITE) ? 'w' : '-');
        /** @noinspection NestedTernaryOperatorInspection */
        $info .= (($perms & self::PERM_OWNER_EXEC)
            ? (($perms & self::PERM_OWNER_EXEC_STICKY) ? 's' : 'x')
            : (($perms & self::PERM_OWNER_EXEC_STICKY) ? 'S' : '-'));

        // Group
        $info .= (($perms & self::PERM_GROUP_READ) ? 'r' : '-');
        $info .= (($perms & self::PERM_GROUP_WRITE) ? 'w' : '-');
        /** @noinspection NestedTernaryOperatorInspection */
        $info .= (($perms & self::PERM_GROUP_EXEC)
            ? (($perms & self::PERM_GROUP_EXEC_STICKY) ? 's' : 'x')
            : (($perms & self::PERM_GROUP_EXEC_STICKY) ? 'S' : '-'));

        // All
        $info .= (($perms & self::PERM_ALL_READ) ? 'r' : '-');
        $info .= (($perms & self::PERM_ALL_WRITE) ? 'w' : '-');
        /** @noinspection NestedTernaryOperatorInspection */
        $info .= (($perms & self::PERM_ALL_EXEC)
            ? (($perms & self::PERM_ALL_EXEC_STICKY) ? 't' : 'x')
            : (($perms & self::PERM_ALL_EXEC_STICKY) ? 'T' : '-'));

        return $info;
    }

    /**
     * Removes a directory (and its contents) recursively.
     * Contributed by Askar (ARACOOL) <https://github.com/ARACOOOL>
     *
     * @param string $dir              The directory to be deleted recursively
     * @param bool   $traverseSymlinks Delete contents of symlinks recursively
     * @return bool
     * @throws RuntimeException
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function rmDir(string $dir, bool $traverseSymlinks = true): bool
    {
        if (!file_exists($dir)) {
            return true;
        }

        if (!is_dir($dir)) {
            throw new Exception('Given path is not a directory');
        }

        if ($traverseSymlinks || !is_link($dir)) {
            $list = (array)scandir($dir, SCANDIR_SORT_NONE);
            foreach ($list as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }

                $currentPath = $dir . '/' . (string)$file;

                if (is_dir($currentPath)) {
                    $this->rmDir($currentPath, $traverseSymlinks);
                } elseif (!unlink($currentPath)) {
                    throw new Exception('Unable to delete ' . $currentPath);
                }
            }
        }

        // Windows treats removing directory symlinks identically to removing directories.
        if (!defined('PHP_WINDOWS_VERSION_MAJOR') && is_link($dir)) {
            if (!unlink($dir)) {
                throw new Exception('Unable to delete ' . $dir);
            }
        } elseif (!rmdir($dir)) {
            throw new Exception('Unable to delete ' . $dir);
        }

        return true;
    }

    /**
     * Binary safe to open file
     *
     * @param string $filepath
     * @return null|string
     * @deprecated Use \file_get_contents()
     */
    public function openFile(string $filepath): ?string
    {
        $contents = null;

        if (($realPath = realpath($filepath)) && $handle = fopen($realPath, 'rb')) {
            $contents = (string)fread($handle, (int)filesize($realPath));
            fclose($handle);
        }

        return $contents ?: null;
    }

    /**
     * Quickest way for getting first file line
     *
     * @param string $filepath
     * @return string|null
     */
    public function firstLine(string $filepath): ?string
    {
        if (file_exists($filepath) && $cacheRes = fopen($filepath, 'rb')) {
            $firstLine = fgets($cacheRes);
            fclose($cacheRes);

            return (string)$firstLine ?: null;
        }

        return null;
    }

    /**
     * Set the writable bit on a file to the minimum value that allows the user running PHP to write to it.
     *
     * @param string $filename The filename to set the writable bit on
     * @param bool   $writable Whether to make the file writable or not
     * @return bool
     */
    public function writable(string $filename, bool $writable = true): bool
    {
        return $this->setPerms($filename, $writable, 2);
    }

    /**
     * Set the readable bit on a file to the minimum value that allows the user running PHP to read to it.
     *
     * @param string $filename The filename to set the readable bit on
     * @param bool   $readable Whether to make the file readable or not
     * @return bool
     */
    public function readable(string $filename, bool $readable = true): bool
    {
        return $this->setPerms($filename, $readable, 4);
    }

    /**
     * Set the executable bit on a file to the minimum value that allows the user running PHP to read to it.
     *
     * @param string $filename   The filename to set the executable bit on
     * @param bool   $executable Whether to make the file executable or not
     * @return bool
     */
    public function executable(string $filename, bool $executable = true): bool
    {
        return $this->setPerms($filename, $executable, 1);
    }

    /**
     * Returns size of a given directory in bytes.
     *
     * @param string $dir
     * @return int
     */
    public function dirSize(string $dir): int
    {
        $size = 0;

        $flags = FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::SKIP_DOTS;

        $dirIterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, $flags));

        /** @var SplFileInfo $splFileInfo */
        foreach ($dirIterator as $splFileInfo) {
            if ($splFileInfo->isFile()) {
                $size += $splFileInfo->getSize();
            }
        }

        return (int)$size;
    }

    /**
     * Returns all paths inside a directory.
     *
     * @param string $dir
     * @return array
     *
     * @SuppressWarnings(PHPMD.UnusedLocalVariable)
     * @SuppressWarnings(PHPMD.ShortMethodName)
     */
    public function ls(string $dir): array
    {
        $contents    = [];

        $flags       = FilesystemIterator::KEY_AS_PATHNAME | FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::SKIP_DOTS;

        $dirIterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, $flags));

        /* @var SplFileInfo $splFileInfo */
        foreach ($dirIterator as $splFileInfo) {
            $contents[] = $splFileInfo->getPathname();
        }

        natsort($contents);
        return $contents;
    }

    /**
     * Nice formatting for computer sizes (Bytes).
     *
     * @param int $bytes    The number in bytes to format
     * @param int $decimals The number of decimal points to include
     * @return  string
     */
    public function format(int $bytes, int $decimals = 2): string
    {
        $exp = 0;
        $value = 0;
        $symbols = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];

        $bytes = (float)$bytes;

        if ($bytes > 0) {
            $exp = (int)floor(log($bytes) / log(1024));
            $value = ($bytes / (1024 ** floor($exp)));
        }

        if ($symbols[$exp] === 'B') {
            $decimals = 0;
        }

        return number_format($value, $decimals, '.', '') . ' ' . $symbols[$exp];
    }

    /**
     * @param string $filename
     * @param bool   $isFlag
     * @param int    $perm
     * @return bool
     */
    protected function setPerms(string $filename, bool $isFlag, int $perm): bool
    {
        $stat = @stat($filename);

        if ($stat === false) {
            return false;
        }

        // We're on Windows
        if ((new System())->isWin()) {
            return true;
        }

        [$myuid, $mygid] = [posix_geteuid(), posix_getgid()];

        $isMyUid = $stat['uid'] === $myuid;
        $isMyGid = $stat['gid'] === $mygid;

        if ($isFlag) {
            // Set only the user writable bit (file is owned by us)
            if ($isMyUid) {
                return chmod($filename, fileperms($filename) | intval('0' . $perm . '00', 8));
            }

            // Set only the group writable bit (file group is the same as us)
            if ($isMyGid) {
                return chmod($filename, fileperms($filename) | intval('0' . $perm . $perm . '0', 8));
            }

            // Set the world writable bit (file isn't owned or grouped by us)
            return chmod($filename, fileperms($filename) | intval('0' . $perm . $perm . $perm, 8));
        }

        // Set only the user writable bit (file is owned by us)
        if ($isMyUid) {
            $add = intval("0{$perm}{$perm}{$perm}", 8);
            return $this->chmod($filename, $perm, $add);
        }

        // Set only the group writable bit (file group is the same as us)
        if ($isMyGid) {
            $add = intval("00{$perm}{$perm}", 8);
            return $this->chmod($filename, $perm, $add);
        }

        // Set the world writable bit (file isn't owned or grouped by us)
        $add = intval("000{$perm}", 8);
        return $this->chmod($filename, $perm, $add);
    }

    /**
     * Chmod alias
     *
     * @param string $filename
     * @param int    $perm
     * @param int    $add
     * @return bool
     */
    protected function chmod(string $filename, int $perm, int $add): bool
    {
        return chmod($filename, (fileperms($filename) | intval('0' . $perm . $perm . $perm, 8)) ^ $add);
    }

    /**
     * Returns
     *
     * @param string|null $path
     * @return string
     */
    public function ext(?string $path): string
    {
        if (!$path) {
            return '';
        }

        if (strpos($path, '?') !== false) {
            $path = (string)preg_replace('#\?(.*)#', '', $path);
        }

        $ext = pathinfo($path, PATHINFO_EXTENSION);
        $ext = strtolower($ext);

        return $ext;
    }

    /**
     * Returns name of file with ext from FS pathname
     *
     * @param string|null $path
     * @return string
     */
    public function base(?string $path): string
    {
        return pathinfo((string)$path, PATHINFO_BASENAME);
    }

    /**
     * Returns filename without ext from FS pathname
     *
     * @param string|null $path
     * @return string
     */
    public function filename(?string $path): string
    {
        return pathinfo((string)$path, PATHINFO_FILENAME);
    }

    /**
     * Returns name for directory from FS pathname
     *
     * @param string|null $path
     * @return string
     */
    public function dirName(?string $path): string
    {
        return pathinfo((string)$path, PATHINFO_DIRNAME);
    }

    /**
     * Returns realpath (smart analog of PHP \realpath())
     *
     * @param string|null $path
     * @return string|null
     */
    public function real(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        $result = realpath($path);
        return $result ?: null;
    }

    /**
     * Function to strip trailing / or \ in a pathname
     *
     * @param string|null $path   The path to clean.
     * @param string      $dirSep Directory separator (optional).
     * @return  string
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    public function clean(?string $path, string $dirSep = DIRECTORY_SEPARATOR): string
    {
        if (!$path) {
            return '';
        }

        $path = trim($path);

        if (($dirSep === '\\') && ($path[0] === '\\') && ($path[1] === '\\')) {
            $path = "\\" . preg_replace('#[/\\\\]+#', $dirSep, $path);
        } else {
            $path = (string)preg_replace('#[/\\\\]+#', $dirSep, $path);
        }

        return $path;
    }

    /**
     * Strip off the extension if it exists.
     *
     * @param string $path
     * @return string
     */
    public function stripExt(string $path): string
    {
        $reg = '/\.' . preg_quote($this->ext($path), '') . '$/';
        return (string)preg_replace($reg, '', $path);
    }

    /**
     * Check is current path directory
     *
     * @param string $path
     * @return bool
     */
    public function isDir(string $path): bool
    {
        if (!$path) {
            return false;
        }

        $path = $this->clean($path);
        return is_dir($path);
    }

    /**
     * Find relative path of file (remove root part)
     *
     * @param string      $path
     * @param string|null $rootPath
     * @param string      $forceDS
     * @return string
     */
    public function getRelative(string $path, ?string $rootPath = null, string $forceDS = DIRECTORY_SEPARATOR): string
    {
        // Cleanup file path
        $cleanedPath = $this->clean((string)$this->real($path), $forceDS);

        // Cleanup root path
        $rootPath = $rootPath ?: (new System())->getDocRoot();
        $rootPath = $this->clean((string)$this->real((string)$rootPath), $forceDS);

        // Remove root part
        $relPath = (string)preg_replace('#^' . preg_quote($rootPath, '\\') . '#', '', $cleanedPath);

        return ltrim($relPath, $forceDS);
    }

    /**
     * Returns clean realpath if file or directory exists
     *
     * @param string|null $path
     * @return bool
     */
    public function isReal(?string $path): bool
    {
        if (!$path) {
            return false;
        }

        $expected = $this->clean((string)$this->real($path));
        $actual   = $this->clean($path);

        return $expected ? $expected === $actual : false;
    }


    /**
     * Deletes a folder with all of its files.
     *
     * @param string $folder Path to the folder.
     * @return bool
     */
    public function deleteFolder(string $folder): bool
    {
        $files = new RecursiveIteratorIterator((new RecursiveDirectoryIterator($folder, RecursiveDirectoryIterator::SKIP_DOTS)), RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
                continue;
            }

            unlink($file->getRealPath());
        }

        return rmdir($folder);
    }

    /**
     * @param  $filename
     *   Path to the file.
     * @return false|string|null
     * @throws Exception
     */
    public function loadFileContents($filename)
    {
        if (!empty($filename) && is_file($filename) && file_exists($filename)) {
            $file = file_get_contents($filename);
        }
        return $file ?? null;
    }

    public function getAllDirs($path): array
    {

        ## - @author http://stackoverflow.com/questions/7497733/how-can-use-php-to-check-if-a-directory-is-empty
        $iterate = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
            RecursiveIteratorIterator::CATCH_GET_CHILD // Ignore "Permission denied"
        );

        $paths = [$path];
        foreach ($iterate as $this_path => $dir) {
            if ($dir->isDir()) {
                $paths[] = $this_path;
            }
        }

        ## - Return found paths
        return $paths;
    }

    public function getFilesInDirectory($path = null, $extension = null){

        $grabFiles = function ($fileInfo){
            return [
                'name' => $fileInfo->getBasename(),
                'file' => $fileInfo->getPathname(),
                'type' => $fileInfo->getExtension(),
            ];
        };

        // validate extension
        $extensions = [];
        if (!empty($extension)){
            if (is_array($extension)){
                foreach ($extension as $i => $e)
                {
                    $extensions[] = str_replace('.', '', $e);
                }
            }elseif (is_string($extension)){
                $extensions[] = str_replace('.', '', $extension);
            }
        }

        $files = [];
        foreach ((new DirectoryIterator($path)) as $fileInfo)
        {
            // skip directories
            if (!$fileInfo->isDot())
            {
                if (!empty($extensions)) {
                    // loop through given extensions
                    foreach ($extensions as $r => $ext)
                    {
                        if ($fileInfo->getExtension() == $ext)
                        {
                            // if file extension is met
                            $files[] = $grabFiles($fileInfo);
                        }
                    }
                }else{
                    $files[] = $grabFiles($fileInfo);
                }
            }
        }

        return (new Transfigure())->toObject([
            'list'  => $files,
            'one'   => $files[array_rand($files)], // randomize file output
        ]);
    }


    /**
     * Replace date variable in dir path.
     *
     * @param string $dir
     *
     * @return string
     */
    public function formatDirectory(string $dir): string
    {
        $replacements = [
            '{Y}' => date('Y'),
            '{m}' => date('m'),
            '{d}' => date('d'),
            '{H}' => date('H'),
            '{i}' => date('i'),
            '{s}' => date('s'),
        ];

        return str_replace(array_keys($replacements), $replacements, $dir);
    }


}
