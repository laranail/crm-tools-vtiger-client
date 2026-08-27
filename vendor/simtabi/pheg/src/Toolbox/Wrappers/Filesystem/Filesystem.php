<?php declare(strict_types=1);

namespace Simtabi\Pheg\Toolbox\Wrappers\Filesystem;

/**
 * Copyright 2020 Martin Neundorfer (Neunerlei)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * Last modified: 2020.03.11 at 22:01
 */

use DateTime;
use EmptyIterator;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionObject;
use RegexIterator;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem as fs;
use Traversable;

class Filesystem
{

    /**
     * The class that is used as file system implementation.
     * Can be used for custom extensions
     *
     * @var string
     */
    public static $fsClass = fs::class;

    /**
     * The instance of the file system
     *
     * @var fs
     */
    protected static $fs;

    public function __construct()
    {
    }

    /**
     * Copies a file or a directory.
     * If the target file is older than the origin file, it's always overwritten.
     *
     * @param   string  $origin  The path to the file or directory to copy
     * @param   string  $target  The pat to copy the file or directory to
     */
    public function copy(string $origin, string $target): void
    {
        $originIsLocal = stream_is_local($origin) || 0 === stripos($origin, 'file://');
        if ($originIsLocal && is_dir($origin)) {
            static::getFs()->mirror($origin, $target);
        } else {
            static::getFs()->copy($origin, $target, true);
        }
    }

    /**
     * Creates a directory recursively.
     *
     * @param   string|iterable  $dirs  The directory path
     * @param   int              $mode  The permissions to create the directories with
     *
     * @throws IOException On any directory creation failure
     */
    public function mkdir($dirs, int $mode = 0777): void
    {
        static::getFs()->mkdir($dirs, $mode);
    }

    /**
     * Checks the existence of files or directories.
     *
     * @param   string|iterable  $files  A filename, an array of files, or a \Traversable instance to check
     *
     * @return bool true if the file(s) exist, false otherwise
     */
    public function exists($files): bool
    {
        return static::getFs()->exists($files);
    }

    /**
     * Tells whether a file or list of files exists and is readable.
     *
     * @param   string|string[]  $files  The files to check for permissions
     *
     * @return bool
     * @throws IOException When windows path is longer than 258 characters
     */
    public function isReadable($files): bool
    {
        $checker = static function (string $filename): bool {
            // I'm working with symfony for years now, and I still don't get why this method is private o.O
            if (($ref = new ReflectionObject(static::getFs()))->hasMethod('isReadable')) {
                ($m = $ref->getMethod('isReadable'))->setAccessible(true);

                return $m->invoke(static::getFs(), $filename);
            }

            return is_readable($filename);
        };

        foreach (static::toIterable($files) as $file) {
            if (! $checker((string)$file)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Tells whether a file or list of files is writable
     *
     * @param   string|string[]  $files  The files to check for permissions
     *
     * @return bool
     */
    public function isWritable($files): bool
    {
        foreach (static::toIterable($files) as $file) {
            if ((file_exists((string)$file) && ! is_writable((string)$file)) ||
                (! file_exists((string)$file) && ! is_writable(dirname((string)$file)))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Sets access and modification time of file.
     *
     * @param   string|iterable   $files A filename, an array of files, or a \Traversable instance to create
     * @param   int|null|DateTime $time  The touch time, if not supplied the current system time is used
     * @param   int|null|DateTime $atime The access time, if not supplied the current system time is used
     *
     * @throws IOException When touch fails
     */
    public function touch($files, $time = null, $atime = null): void
    {
        if (! empty($time) && $time instanceof DateTime) {
            $time = $time->getTimestamp();
        }
        if (! empty($atime) && $atime instanceof DateTime) {
            $atime = $atime->getTimestamp();
        }
        static::getFs()->touch($files, $time, $atime);
    }

    /**
     * Removes files or directories.
     *
     * @param   string|iterable  $files  A filename, an array of files, or a \Traversable instance to remove
     *
     * @throws IOException When removal fails
     */
    public function remove($files): void
    {
        static::getFs()->remove($files);
    }

    /**
     * Removes all contents from a given directory without removing the element itself
     *
     * @param   string|iterable  $files  either a single or multiple directories to flush
     *
     * @throws IOException When removal fails
     */
    public function flushDirectory($files): void
    {
        foreach (static::toIterable($files) as $directory) {
            if (is_dir((string)$directory)) {
                static::getFs()->remove(static::getDirectoryIterator((string)$directory, true));
            }
        }
        clearstatcache();
    }

    /**
     * Helper to create a directory iterator either for a single folder or recursively. Dots will
     * automatically be skipped. It can also find only files matching a regular expression. By default the folder will
     * come after the children, which can be toggled using the options.
     *
     * @param string $directory     The directory to iterate
     * @param bool   $recursive     default: false | If set to true the directory will be iterated recursively
     * @param array  $options       Additional configuration options:
     *                              - regex (string) default: "" | Optional Regex pattern the returned files have to
     *                              match
     *                              - dirFirst (bool) default: FALSE | By default the folder is returned after its
     *                              contents. If you set this to true, the folder will be returned first
     *
     * @return iterable
     */
    public function getDirectoryIterator(string $directory, bool $recursive = false, array $options = []): iterable
    {
        // Check if we got a directory
        if (! is_dir($directory)) {
            return new EmptyIterator();
        }

        // Create the iterator
        $it = $recursive
            ? new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
                ! empty($options['dirFirst']) || in_array('dirFirst', $options, true) ?
                    RecursiveIteratorIterator::SELF_FIRST : RecursiveIteratorIterator::CHILD_FIRST)
            : new FilesystemIterator($directory);

        // Apply a file regex if required
        if (! empty($options['regex']) && is_string($options['regex'])) {
            return new RegexIterator($it, $options['regex']);
        }

        return $it;
    }

    /**
     * Returns the unix file permissions for a given file like 0777 as a string.
     *
     * @param   string  $filename  The name of the file to get the permissions for
     *
     * @return string
     *
     * @throws IOException When the permission lookup fails
     */
    public function getPermissions(string $filename): string
    {
        if (! file_exists($filename)) {
            throw new IOException('Could not get the permissions of file: ' . $filename .
                ' because the file does not exist!');
        }

        // Convert the permissions to a readable string
        $perms = fileperms($filename);
        if ($perms === false) {
            throw new IOException('Could not get the permissions of file: ' . $filename .
                ' because: ' . error_get_last());
        }

        // Format and be done
        return substr(sprintf('%o', $perms), -4);
    }

    /**
     * Can be used to set the unix permissions for a file or folder
     *
     * @param   string|iterable  $files      The name of the file or a list of files to set the permissions for
     * @param   int              $mode       The unix permissions to set like 0777
     * @param   bool             $recursive  default TRUE: FALSE if directories should NOT be traversed recursively
     *
     * @throws IOException When the permission update fails
     */
    public function setPermissions($files, int $mode, bool $recursive = true): void
    {
        // I reimplement the logic, because the symfony component crashes if you make a directory un-readable
        foreach (static::toIterable($files) as $file) {
            if ($recursive && is_dir((string)$file) && ! is_link((string)$file)) {
                static::setPermissions(new \FilesystemIterator((string)$file), $mode, true);
            }
            if (true !== @chmod((string)$file, $mode & ~0000)) {
                throw new IOException(sprintf('Failed to chmod file "%s".', (string)$file), 0, null, (string)$file);
            }
        }
        clearstatcache();
    }

    /**
     * Returns the numeric unix user id for the given file or folder
     *
     * @param   string  $filename
     *
     * @return int
     * @throws IOException When the lookup fails
     */
    public function getOwner(string $filename): int
    {
        if (! file_exists($filename)) {
            throw new IOException('Could not get the owner of file: ' . $filename .
                ' because the file does not exist! Permissions are: ' . static::getPermissions($filename));
        }

        $owner = @fileowner($filename);
        if ($owner === false) {
            throw new IOException('Could not get the owner of file: ' . $filename .
                ' because: ' . error_get_last()['message'] . ' Permissions are: ' . static::getPermissions($filename));
        }

        return $owner;
    }

    /**
     * Can be used to update the owner of a given file or folder
     *
     * @param   string|string[]  $files      The file, or list of files to set the owner for
     * @param   string|int       $user       The unix user to set for the file
     * @param   bool             $recursive  default TRUE: FALSE if directories should NOT be traversed recursively
     *
     * @throws IOException When the group update fails
     */
    public function setOwner($files, $user, bool $recursive = true): void
    {
        static::getFs()->chown($files, $user, $recursive);
        clearstatcache();
    }

    /**
     * Returns the numeric unix user group for the given filename
     *
     * @param   string  $filename
     *
     * @return int
     * @throws IOException When the lookup fails
     */
    public function getGroup(string $filename): int
    {
        if (! file_exists($filename)) {
            throw new IOException('Could not get the group of file: ' . $filename .
                ' because the file does not exist! Permissions are: ' . static::getPermissions($filename));
        }

        $group = @filegroup($filename);
        if ($group === false) {
            throw new IOException('Could not get the group of file: ' . $filename .
                ' because: ' . error_get_last()['message'] . ' Permissions are: ' . static::getPermissions($filename));
        }

        return $group;
    }

    /**
     * Can be used to update the group of a given file or folder
     *
     * @param   string|string[]  $files      The file, or list of files to set the group for
     * @param   string|int       $group      The unix group to set for the file
     * @param   bool             $recursive  default TRUE: FALSE if directories should NOT be traversed recursively
     *
     * @throws IOException When the group update fails
     */
    public function setGroup($files, $group, bool $recursive = true): void
    {
        static::getFs()->chgrp($files, $group, $recursive);
        clearstatcache();
    }

    /**
     * A wrapper around file_get_contents which reads the contents, but handles unreadable or non existing
     * files with speaking exceptions.
     *
     * @param   string         $filename  The path to the file to read
     * @param   null|resource  $context   A valid context resource created with stream_context_create
     *
     * @return string
     * @throws FileNotFoundException
     * @throws IOException
     */
    public function readFile(string $filename, $context = null): string
    {
        if (! static::isReadable($filename)) {
            if (! static::exists($filename)) {
                throw new FileNotFoundException('Could not read file: ' . $filename . ' because it does not exist!');
            }
            throw new IOException('Could not read file: ' . $filename .
                ' - Permission denied! Permissions: ' . static::getPermissions($filename));
        }

        // Try to read the file
        $result = @file_get_contents($filename, false, $context);
        if ($result === false) {
            throw new IOException('Could not read file: ' . $filename . ' because: ' . error_get_last()['message']);
        }

        return $result;
    }

    /**
     * A wrapper around file() which handles non existing, or unreadable files with speaking exceptions.
     *
     * @param   string         $filename  The path to the file to read
     * @param   null|resource  $context   A valid context resource created with stream_context_create
     *
     * @return array
     * @throws FileNotFoundException
     * @throws IOException
     * @see \file()
     */
    public function readFileAsLines(string $filename, $context = null): array
    {
        // Make sure we can read the file
        if (! is_readable($filename)) {
            if (! is_file($filename)) {
                throw new FileNotFoundException('Could not read file: ' . $filename . ' because it does not exist!');
            }
            throw new IOException('Could not read file: ' . $filename .
                ' - Permission denied! Permissions: ' . static::getPermissions($filename));
        }

        // Read lines
        $lines = @file($filename, 0, $context);
        if ($lines === false) {
            throw new IOException('Could not read file: ' . $filename . ' because: ' . error_get_last()['message']);
        }

        return $lines;
    }

    /**
     * Writes the given content into a file on your file system.
     *
     * @param   string  $filename  The path to the file to write
     * @param   string  $content   The data to write into the file
     *
     * @throws IOException
     */
    public function writeFile(string $filename, $content): void
    {
        static::getFs()->dumpFile($filename, $content);
    }

    /**
     * Appends content to an existing file. By default all content will be added on a new line
     *
     * @param   string           $filename   The path to the file to write
     * @param   string|resource  $content    The content to append
     * @param   bool             $asNewLine  If true the content will be added on a new line
     *
     * @throws IOException If the file is not writable
     */
    public function appendToFile(string $filename, $content, bool $asNewLine = true): void
    {
        static::getFs()->appendToFile($filename, $asNewLine && static::exists($filename) ? PHP_EOL . $content : $content);
    }

    /**
     * Returns the instance of the file system class
     *
     * @return fs
     */
    public function getFs(): fs
    {
        return static::$fs ?? (static::$fs = new static::$fsClass());
    }

    /**
     * Converts the given file/files to an iterable object
     *
     * @param $files
     *
     * @return iterable
     */
    protected static function toIterable($files): iterable
    {
        return is_array($files) || $files instanceof Traversable ? $files : [$files];
    }
}