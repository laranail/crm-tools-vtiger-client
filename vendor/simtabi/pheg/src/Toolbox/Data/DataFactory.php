<?php declare(strict_types=1);

namespace Simtabi\Pheg\Toolbox\Data;

use Simtabi\Pheg\Toolbox\Data\Types\Factory;
use Simtabi\Pheg\Toolbox\Data\Types\Ini;
use Simtabi\Pheg\Toolbox\Data\Types\JSON;
use Simtabi\Pheg\Toolbox\Data\Types\PhpArray;
use Simtabi\Pheg\Toolbox\Data\Types\Yml;


final class DataFactory
{

    public function __construct() {}

    /**
     * @param mixed|null $data
     * @return Json
     */
    public function json(mixed $data = null): JSON
    {
        if ($data instanceof JSON)
        {
            return $data;
        }

        return new JSON($data);
    }

    /**
     * @param mixed|null $data
     * @return Factory
     */
    public function data(mixed $data = null): Factory
    {
        if ($data instanceof Factory)
        {
            return $data;
        }

        return new Factory($data);
    }

    /**
     * @param mixed|null $data
     * @return Factory
     */
    public function phpArray(mixed $data = null): Factory
    {
        if ($data instanceof PhpArray)
        {
            return $data;
        }

        return new PhpArray($data);
    }

    /**
     * @param mixed|null $data
     * @return Factory
     */
    public function ini(mixed $data = null): Factory
    {
        if ($data instanceof Ini) {
            return $data;
        }

        return new Ini($data);
    }

    /**
     * @param mixed|null $data
     * @return Factory
     */
    public function yml(mixed $data = null): Factory
    {
        if ($data instanceof Yml)
        {
            return $data;
        }

        return new Yml($data);
    }
}