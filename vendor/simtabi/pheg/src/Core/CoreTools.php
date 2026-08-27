<?php declare(strict_types=1);

namespace Simtabi\Pheg\Core;

class CoreTools
{

    /**
     * Default region for telephone utilities
     */
    public const string DEFAULT_REGION = 'KE';

    public const string PHEG_DIR_PATH = __DIR__.'/../../';

    /**
     * @var string
     */
    protected static string $defaultRegion = 'KE';

    public static function getRootPath(int $levels = 2): string
    {
        return dirname( __DIR__ , $levels);
    }

    public static function _e($val){
        return $val;
    }
}
