<?php

namespace Simtabi\Pheg\Toolbox\Intel\Info;

class Server
{
    public function __construct() {}

    /**
     * Get current Php version information
     *
     * @return array
     */
    public function getPhpVersionInfo(): array
    {
        $currentVersionFull = PHP_VERSION;
        preg_match('#^\\d+(\\.\\d+)*#', $currentVersionFull, $filtered);
        $currentVersion     = $filtered[0];

        return [
            'full'    => $currentVersionFull,
            'version' => $currentVersion,
        ];
    }

    /**
     * Check PHP version requirement.
     *
     * @param string|null $minPhpVersion
     * @return array
     */
    public function checkPhpVersion(?string $minPhpVersion = null): array
    {
        $minVersionPhp     = $minPhpVersion;
        $currentPhpVersion = $this->getPhpVersionInfo();
        $supported         = false;

        if (version_compare($currentPhpVersion['version'], $minVersionPhp, '>=') >= 0) {
            $supported = true;
        }

        return [
            'full'      => $currentPhpVersion['full'],
            'current'   => $currentPhpVersion['version'],
            'minimum'   => $minVersionPhp,
            'supported' => $supported,
        ];
    }

    /**
     * Verifies if the mod_rewrite module is enabled
     *
     * @return boolean True if the module is enabled.
     */
    public function isModRewriteEnabled(): bool
    {
        return (bool) isset($_SERVER['HTTP_MOD_REWRITE']) && $_SERVER['HTTP_MOD_REWRITE'] == 'On';
    }

    /**
     * Get package root path
     *
     * @param string|null $resource
     * @param int $levels
     * @return string
     */
    public function getRootPath(?string $resource = null, int $levels = 2): string
    {
        return dirname(__DIR__, $levels) . "/" . (!empty($resource) ? "{$resource}" : "");
    }

    /**
     * Get or all installed apache modules, or check if a
     * given module is installed
     *
     * @param string|null $moduleName
     *
     * @return bool|array
     */
    public function getInstalledApacheModules(?string $moduleName = null): bool|array
    {
        if (function_exists('apache_get_modules')) {
            $moduleName = trim($moduleName);
            $modules    = \apache_get_modules();

            if (empty($moduleName)) {
                return $modules;
            }

            return (bool) array_search($moduleName, $modules);
        }

        return false;
    }

}