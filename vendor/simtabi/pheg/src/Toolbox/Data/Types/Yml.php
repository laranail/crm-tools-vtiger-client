<?php declare(strict_types=1);

namespace Simtabi\Pheg\Toolbox\Data\Types;

use Symfony\Component\Yaml\Yaml;

/**
 * Class Yml
 *
 * @package Simtabi\Pheg\Toolbox\Data
 */
final class Yml extends Factory
{
    /**
     * Utility Method to serialize the given data
     *
     * @param mixed $data The data to serialize
     * @return string The serialized data
     */
    protected function encode($data): string
    {
        return Yaml::dump($data, 10, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK | Yaml::DUMP_NULL_AS_TILDE);
    }

    /**
     * Utility Method to unserialize the given data
     *
     * @param string $string
     * @return mixed
     */
    protected function decode(string $string)
    {
        return Yaml::parse($string);
    }
}
