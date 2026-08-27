<?php declare(strict_types=1);

namespace Simtabi\Pheg\Toolbox\Data\Types;

/**
 * Class JSON
 *
 * @package Simtabi\Pheg\Toolbox\Data
 */
final class JSON extends Factory
{
    /**
     * Utility Method to unserialize the given data
     *
     * @param string $string
     * @return mixed
     */
    protected function decode(string $string)
    {
        return \json_decode($string, true, 512, \JSON_BIGINT_AS_STRING);
    }

    /**
     * Does the real json encoding adding human readability. Supports automatic indenting with tabs
     *
     * @param mixed $data
     * @return string
     */
    protected function encode($data): string
    {
        return (string)\json_encode($data, \JSON_PRETTY_PRINT | \JSON_BIGINT_AS_STRING);
    }
}
