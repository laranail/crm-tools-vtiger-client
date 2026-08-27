<?php declare(strict_types=1);

namespace Simtabi\Pheg\Toolbox;

use Simtabi\Pheg\Toolbox\Media\File\FileSystem;

/**
 * Class Sys
 *
 * @package Simtabi\Pheg\Toolbox
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
final class System
{

    private FileSystem $fileSystem;

    public function __construct()
    {
        $this->fileSystem = new FileSystem;
    }

    /**
     * Check is current OS Windows
     *
     * @return bool
     */
    public function isWin(): bool
    {
        return strncasecmp(PHP_OS_FAMILY, 'WIN', 3) === 0 || DIRECTORY_SEPARATOR === '\\';
    }

    /**
     * Check is current user ROOT
     *
     * @return bool
     */
    public function isRoot(): bool
    {
        if ($this->isFunc('posix_geteuid')) {
            return 0 === posix_geteuid();
        }

        return false;
    }

    /**
     * Returns current linux user who runs script
     * @return string|null
     */
    public function getUserName(): ?string
    {
        $userInfo = posix_getpwuid(posix_geteuid());
        if ($userInfo && isset($userInfo['name'])) {
            return $userInfo['name'];
        }

        return null;
    }

    /**
     * Returns a home directory of current user.
     *
     * @return string|null
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    public function getHome(): ?string
    {
        $userInfo = posix_getpwuid(posix_geteuid());
        if ($userInfo && isset($userInfo['dir'])) {
            return $userInfo['dir'];
        }

        if (array_key_exists('HOMEDRIVE', $_SERVER)) {
            return $_SERVER['HOMEDRIVE'] . $_SERVER['HOMEPATH'];
        }

        return $_SERVER['HOME'] ?? null;
    }

    /**
     * Alias fo ini_set function
     *
     * @param string $phpIniKey
     * @param string $newValue
     * @return bool
     */
    public function iniSet(string $phpIniKey, string $newValue): bool
    {
        if ($this->isFunc('ini_set')) {
            return (new Filter())->bool(ini_set($phpIniKey, $newValue));
        }

        return false;
    }

    /**
     * Alias fo ini_get function
     *
     * @param string $varName
     * @return string
     */
    public function iniGet(string $varName): string
    {
        return (string)ini_get($varName);
    }

    /**
     * Checks if function exists and callable
     *
     * @param string|\Closure $funcName
     * @return bool
     */
    public function isFunc($funcName): bool
    {
        $isEnabled = true;
        if (is_string($funcName)) {
            $isEnabled = stripos($this->iniGet('disable_functions'), strtolower(trim($funcName))) === false;
        }

        return $isEnabled && (is_callable($funcName) || (is_string($funcName) && function_exists($funcName)));
    }

    /**
     * Set PHP execution time limit (doesn't work in safe mode)
     *
     * @param int $newLimit
     */
    public function setTime(int $newLimit = 0): void
    {
        $this->iniSet('set_time_limit', (string)$newLimit);
        $this->iniSet('max_execution_time', (string)$newLimit);

        if ($this->isFunc('set_time_limit')) {
            set_time_limit($newLimit);
        }
    }

    /**
     * Set new memory limit
     *
     * @param string $newLimit
     */
    public function setMemory(string $newLimit = '256M'): void
    {
        $this->iniSet('memory_limit', $newLimit);
    }

    /**
     * Compares PHP versions
     *
     * @param string $version
     * @param string $current
     * @return bool
     */
    public function isPHP(string $version, string $current = PHP_VERSION): bool
    {
        $version = trim($version, '.');
        return (bool)preg_match('#^' . preg_quote($version, '') . '#i', $current);
    }

    /**
     * Get usage memory
     *
     * @param bool $isPeak
     * @return string
     */
    public function getMemory(bool $isPeak = true): string
    {
        if ($isPeak) {
            $memory = memory_get_peak_usage(false);
        } else {
            $memory = memory_get_usage(false);
        }

        return $this->fileSystem->format($memory);
    }

    /**
     * Returns current document root
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     * @return string|null
     */
    public function getDocRoot(): ?string
    {
        $result = $_SERVER['DOCUMENT_ROOT'] ?? '.';
        $result = $this->fileSystem->clean($result);
        $result = $this->fileSystem->real($result);

        if (!$result) {
            $result = $this->fileSystem->real('.');
        }

        return $result;
    }

    /**
     * Returns true when Xdebug is supported or
     * the runtime used is PHPDBG (PHP >= 7.0).
     *
     * @return bool
     */
    public function canCollectCodeCoverage(): bool
    {
        return $this->hasXdebug() || $this->hasPHPDBGCodeCoverage();
    }

    /**
     * Returns the path to the binary of the current runtime.
     * Appends ' --php' to the path when the runtime is HHVM.
     *
     * @return string
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    public function getBinary(): string
    {
        if ($customPath = (new Env)->string('PHP_BINARY_CUSTOM')) {
            return $customPath;
        }

        // HHVM
        if ($this->isHHVM()) {
            if (($binary = getenv('PHP_BINARY')) === false) {
                $binary = PHP_BINARY;
            }
            return escapeshellarg($binary) . ' --php';
        }

        if (defined('PHP_BINARY')) {
            return escapeshellarg(PHP_BINARY);
        }

        $binaryLocations = [
            PHP_BINDIR . '/php',
            PHP_BINDIR . '/php-cli.exe',
            PHP_BINDIR . '/php.exe',
        ];

        foreach ($binaryLocations as $binary) {
            if (is_readable($binary)) {
                return $binary;
            }
        }

        return 'php';
    }

    /**
     * Return type and version of current PHP
     *
     * @return string
     */
    public function getNameWithVersion(): string
    {
        $name    = $this->getName();
        $version = $this->getVersion();

        return trim("{$name} {$version}");
    }

    /**
     * Returns type of PHP
     *
     * @return string
     */
    public function getName(): string
    {
        if ($this->isHHVM()) {
            return 'HHVM';
        }

        if ($this->isPHPDBG()) {
            return 'PHPDBG';
        }

        return 'PHP';
    }

    /**
     * Return URL of PHP official web-site. It depends of PHP vendor.
     *
     * @return string
     */
    public function getVendorUrl(): string
    {
        if ($this->isHHVM()) {
            return 'http://hhvm.com/';
        }

        return 'http://php.net/';
    }

    /**
     * Returns current PHP version
     *
     * @return string|null
     */
    public function getVersion(): ?string
    {
        return defined('PHP_VERSION') ? PHP_VERSION : null;
    }

    /**
     * Returns true when the runtime used is PHP and Xdebug is loaded.
     *
     * @return bool
     */
    public function hasXdebug(): bool
    {
        return ($this->isRealPHP() || $this->isHHVM()) && extension_loaded('xdebug');
    }

    /**
     * Returns true when the runtime used is HHVM.
     *
     * @return bool
     */
    public function isHHVM(): bool
    {
        return defined('HHVM_VERSION');
    }

    /**
     * Returns true when the runtime used is PHP without the PHPDBG SAPI.
     *
     * @return bool
     */
    public function isRealPHP(): bool
    {
        return !$this->isHHVM() && !$this->isPHPDBG();
    }

    /**
     * Returns true when the runtime used is PHP with the PHPDBG SAPI.
     *
     * @return bool
     */
    public function isPHPDBG(): bool
    {
        return PHP_SAPI === 'phpdbg' && !$this->isHHVM();
    }

    /**
     * Returns true when the runtime used is PHP with the PHPDBG SAPI
     * and the phpdbg_*_oplog() functions are available (PHP >= 7.0).
     *
     * @return bool
     */
    public function hasPHPDBGCodeCoverage(): bool
    {
        return $this->isPHPDBG() && function_exists('phpdbg_start_oplog');
    }
}
