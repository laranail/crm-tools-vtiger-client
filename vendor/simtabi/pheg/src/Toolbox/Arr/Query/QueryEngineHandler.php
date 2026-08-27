<?php

namespace Simtabi\Pheg\Toolbox\Arr\Query;

use Simtabi\Pheg\Toolbox\Arr\Query\Exceptions\InvalidJsonException;
use Simtabi\Pheg\Toolbox\Arr\Query\Exceptions\FileNotFoundException;
use Simtabi\Pheg\Toolbox\Arr\Query\QueryEngine as QE;

class QueryEngineHandler extends QE
{

    /**
     * Parse valid JSON data to an Array
     *
     * @param string $data
     * @return array|mixed
     * @throws InvalidJsonException
     */
    public function parseData($data)
    {
        if (is_null($data)) return [];

        $data = json_decode($data, true);
        if (json_last_error() != JSON_ERROR_NONE) throw new InvalidJsonException();

        return $data;
    }

    /**
     * Parse data from give file or URL
     *
     * @param string $jsonFile
     * @return array|mixed
     * @throws FileNotFoundException
     * @throws InvalidJsonException
     */
    public function readPath($jsonFile)
    {
        if (is_null($jsonFile)) {
            throw new FileNotFoundException();
        }

        $rawData = null;

        if (filter_var($jsonFile, FILTER_VALIDATE_URL)) {
            $rawData = file_get_contents($jsonFile);
        }

        if (file_exists($jsonFile)) {
            $path = pathinfo($jsonFile);
            $extension = isset($path['extension']) ? $path['extension'] : null;

            if ($extension != 'json') {
                throw new InvalidJsonException();
            }

            $rawData = file_get_contents($jsonFile);
        }

        if (is_null($rawData)) throw new FileNotFoundException();

        $data = json_decode($rawData, true);

        if (json_last_error() !== JSON_ERROR_NONE) throw new InvalidJsonException();

        return $data;
    }
}
