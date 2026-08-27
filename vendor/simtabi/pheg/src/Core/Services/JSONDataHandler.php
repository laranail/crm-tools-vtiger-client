<?php declare(strict_types=1);

namespace Simtabi\Pheg\Core\Services;

use Adbar\Dot;
use DirectoryIterator;
use Simtabi\JsonDB\Services\File\Json2File;
use Simtabi\Pheg\Core\CoreTools;
use stdClass;

class JSONDataHandler
{

    private string $filesPath = '';
    private array  $keys      = [];
    private Dot    $data;



    /**
     * Create class instance
     *
     * @version      1.0
     * @since        1.0
     */
    private static ?self $instance;

    public static function getInstance(): JSONDataHandler|static|null
    {
        if (isset(self::$instance) && !is_null(self::$instance)) {
            return self::$instance;
        } else {

            self::$instance = new static();
            self::$instance->filesPath = CoreTools::PHEG_DIR_PATH.'vendor/annexare/countries-list/data';

            self::$instance->autoloadJSONFiles(
                self::$instance->filesPath
            );

            return self::$instance;
        }
    }

    private function __construct() {}
    private function __clone() {}

    /**
     * @return array
     */
    public function getKeys(): array
    {
        return $this->keys;
    }

    /**
     * @param array $keys
     * @return self
     */
    private function setKeys(array $keys): self
    {
        $this->keys = $keys;
        return $this;
    }

    /**
     * @param null $request
     * @return array|mixed|null
     */
    public function getData($request)
    {
        $request = trim($request);
        $data    = $this->data;

        if ($data->has($request)) {
            return $data->get($request);
        }
        return null;
    }

    /**
     * @param Dot $data
     * @return self
     */
    private function setData(Dot $data): self
    {
        $this->data = $data;
        return $this;
    }

    public function getAll(){
        return $this->data->all();
    }

    private function autoloadJSONFiles(string $directory)
    {

        $directories = [];

        // loop to get all files
        foreach ((new DirectoryIterator($directory)) as $cacheKey => $fileInfo) {
            if (!$fileInfo->isDot()) {

                $filename = $fileInfo->getFilename();
                $fileKey  = str_replace('.json', '', $filename);
                $directories[$fileKey]  = [
                    'filename' => $filename,
                    'pathname' => $fileInfo->getPathname(),
                ];

            }
        }

        // loop to get json file contents
        $array = [];
        $keys  = [];
        foreach ($directories as $cacheKey => $value){
            $array[$cacheKey] = Json2File::fileToArray($value['pathname']);
            $keys[]           = $cacheKey;
        }

        $this->setKeys($keys)->setData(new Dot($array));
        return $this;
    }

}
