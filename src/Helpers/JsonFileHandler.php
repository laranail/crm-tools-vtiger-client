<?php

declare(strict_types=1);

namespace Simtabi\Laranail\CrmTools\VtigerClient\Helpers;

use Illuminate\Database\Eloquent\Collection as EC;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class JsonFileHandler
{
    public function __construct() {}

    public static function deleteFileOrDir($name, $storageDisk = 'local'): bool
    {
        if (Storage::disk($storageDisk)->exists($name)) {
            return Storage::disk($storageDisk)->delete($name);
        }

        return false;
    }

    public static function generateJsonFile($fileName, Collection|EC|array $data, string $dirName, $storageDisk = 'local'): bool|string
    {

        if ((! $data instanceof Collection) && (! $data instanceof EC)) {
            $data = collect($data);
        }

        if (! Storage::disk($storageDisk)->exists($dirName)) {
            Storage::disk($storageDisk)->createDir($dirName);
        } else {
            self::deleteFileOrDir($fileName);
        }

        return Storage::disk($storageDisk)->put($dirName.'/'."$fileName.json", json_encode($data, JSON_PRETTY_PRINT));
    }
}
