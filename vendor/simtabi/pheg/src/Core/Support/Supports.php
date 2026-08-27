<?php declare(strict_types=1);

namespace Simtabi\Pheg\Core\Support;

use Adbar\Dot;
use Simtabi\Pheg\Core\Loader;
use Simtabi\Pheg\Core\Support\Traits\DataHelpersTrait;
use Simtabi\Pheg\Core\Support\Traits\FormHelpersTrait;
use Simtabi\Pheg\Pheg;

class Supports
{
    use DataHelpersTrait;
    use FormHelpersTrait;

    private Loader $loader;
    private Dot $data;
    private Dot $colors;
    private string $key;
    private string|null $default = null;
    private Pheg $pheg;
    private bool $asArray = true;
    private string $fileName;

    /**
     * Create class instance
     *
     * @version      1.0
     * @since        1.0
     */
    private static self $instance;

    public static function getInstance(Pheg $pheg): self
    {
        if (isset(self::$instance) && !is_null(self::$instance)) {
            return self::$instance;
        } else {

            $static         = new static();
            $static->loader = new Loader();
            $static->pheg   = $pheg;

            $data           = $static->registerSupportFiles(['supports'], 'config');
            $static->data   = new Dot($data->loader->init());

            return self::$instance = $static;
        }
    }

    private function __construct() {}
    private function __clone() {}

    private function registerSupportFiles(array $files, string $folder): self
    {
        foreach ($files as $file)
        {
            $this->loader->setFolderName($folder)->setFileNames($file);
        }

        return $this;
    }

    /**
     * @param string $key
     * @return self
     */
    public function setKey(string $key): self
    {
        $this->key = trim($key);
        return $this;
    }

    /**
     * @return string
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * @param mixed $value
     * @return self
     */
    public function setDefault(mixed $value): self
    {
        $this->default = $this->pheg->filter()->trimIfString($value);
        return $this;
    }

    /**
     * @return string
     */
    public function getDefault(): string
    {
        return $this->default;
    }

    public function asArray(bool $status = true): self
    {
        $this->asArray = $status;

        return $this;
    }

    /**
     * @param string $fileName
     * @return self
     */
    public function setFileName(string $fileName): self
    {
        $this->fileName = trim($fileName);
        return $this;
    }

    /**
     * @return string
     */
    public function getFileName(): string
    {
        return $this->fileName;
    }

    public function getData(): object|array
    {

        $data = [];

        if ($this->data->has($this->fileName)) {
            $data = $this->data->get($this->fileName);
        }

        $data = $data[$this->key] ?? [];

        if (!empty($this->default) && (is_array($data) && count($data) >= 1)) {
            $data = $this->pheg->arr()->fetch($this->default, $data);
        }

        return $this->asArray ? $data : $this->pheg->transfigure()->toObject($data);
    }

}
