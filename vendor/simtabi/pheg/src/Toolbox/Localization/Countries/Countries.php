<?php declare(strict_types=1);

namespace Simtabi\Pheg\Toolbox\Localization\Countries;

use Adbar\Dot;
use DirectoryIterator;
use Simtabi\Pheg\Toolbox\Localization\Countries\Traits\WithContinentsTrait;
use Simtabi\Pheg\Toolbox\Localization\Countries\Traits\WithCountriesTrait;
use Simtabi\Pheg\Toolbox\Localization\Countries\Traits\WithCurrenciesTrait;
use Simtabi\Pheg\Toolbox\Localization\Countries\Traits\WithISOCodesTrait;
use Simtabi\Pheg\Toolbox\Localization\Countries\Traits\WithLanguagesTrait;
use Simtabi\Pheg\Core\CoreTools;
use Simtabi\Pheg\Toolbox\Localization\Countries\Traits\WithTimezonesTrait;
use Simtabi\Pheg\Toolbox\Localization\Countries\Traits\WithValidatorsTrait;
use Simtabi\Pheg\Toolbox\JSON\Json2File;

final class Countries
{

    use WithContinentsTrait;
    use WithCountriesTrait;
    use WithCurrenciesTrait;
    use WithISOCodesTrait;
    use WithLanguagesTrait;
    use WithTimezonesTrait;
    use WithValidatorsTrait;

    public const TIMEZONE_AFRICA     = 'africa';
    public const TIMEZONE_AMERICA    = 'america';
    public const TIMEZONE_ANTARCTICA = 'antarctica';
    public const TIMEZONE_ASIA       = 'asia';
    public const TIMEZONE_ATLANTIC   = 'atlantic';
    public const TIMEZONE_AUSTRALIA  = 'australia';
    public const TIMEZONE_EUROPE     = 'europe';
    public const TIMEZONE_INDIAN     = 'indian';
    public const TIMEZONE_PACIFIC    = 'pacific';
    public const TIMEZONE_UTC        = 'utc';

    private string $basePath  = '';
    private bool   $asObject  = false;
    private array  $keys      = [];
    private Dot    $data;
    private array  $raw       = [];
    private array  $loaded    = [];
    private array  $loadFrom  = [];

    public function __construct(?string $basePath = null) {
        if (!empty($basePath) && is_string($basePath)) {
            $this->basePath = $basePath;
        }else{
            $this->basePath = CoreTools::PHEG_DIR_PATH .'../../';
        }

        $this->setLoadFrom([
            'countries' => $this->basePath.'annexare/countries-list/data',
            'currency'  => [
                CoreTools::PHEG_DIR_PATH.'/data/currency'
            ],
        ])->initialize();
    }

    private function initialize(): static
    {

        $autoloadJSONFiles = function (string $directory, $id) {

            $data = [];
            $name = function ($filename) {
                return str_replace('.json', '', $filename);
            };

            // loop to get all files
            foreach ((new DirectoryIterator($directory)) as $cacheKey => $fileInfo) {
                if (!$fileInfo->isDot()) {
                    $filename               = $fileInfo->getFilename();
                    $_name                  = $name($filename);
                    $this->raw[$id][$_name] = [
                        'filename' => $filename,
                        'pathname' => $fileInfo->getPathname(),
                    ];
                    $this->setKeys($_name);
                    $this->setLoaded($id);
                    $data[$_name]           = (new Json2File($fileInfo->getPathname()))->fileToArray();
                }
            }

            return $data;
        };

        $array = [];
        foreach ($this->loadFrom as $key => $item){
            if (is_array($item)) {
                foreach ($item as $value){
                    if (!is_array($value)) {
                        $array[$key] = $autoloadJSONFiles($value, $key);
                    }
                }
            }else{
                $array[$key] = $autoloadJSONFiles($item, $key);
            }
        }
        $this->setData(new Dot($array));
        return $this;
    }

    /**
     * @param array $raw
     * @return self
     */
    public function setRaw(array $raw): self
    {
        $this->raw = $raw;
        return $this;
    }

    /**
     * @return array
     */
    public function getRaw(): array
    {
        return $this->raw;
    }

    /**
     * @param string $loaded
     * @return self
     */
    public function setLoaded(string $loaded): self
    {
        $this->loaded[] = $loaded;
        return $this;
    }

    /**
     * @return array
     */
    public function getLoaded(): array
    {
        return $this->loaded;
    }

    /**
     * @param array $loadFrom
     * @return self
     */
    public function setLoadFrom(array $loadFrom): self
    {
        $this->loadFrom = $loadFrom;
        return $this;
    }

    /**
     * @return array
     */
    public function getLoadFrom(): array
    {
        return $this->loadFrom;
    }

    /**
     * @return array
     */
    public function getKeys(): array
    {
        return $this->keys;
    }

    /**
     * @param string $keys
     * @return self
     */
    private function setKeys(string $keys): self
    {
        $this->keys[] = $keys;
        return $this;
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

    /**
     * @param string $request
     * @return array
     */
    public function getData(string $request): array
    {
        $request = trim($request);
        $data    = [];

        if ($this->data->has($request)) {
            $data = $this->data->get($request);
        }
        return $data;
    }

    public function getAll()
    {
        return $this->data->all();
    }

}
