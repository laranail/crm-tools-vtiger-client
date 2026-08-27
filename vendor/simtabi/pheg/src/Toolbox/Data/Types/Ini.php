<?php declare(strict_types=1);

namespace Simtabi\Pheg\Toolbox\Data\Types;

/**
 * Class Ini
 *
 * @package Simtabi\Pheg\Toolbox\Data
 */
final class Ini extends Factory
{
    /**
     * Utility Method to unserialize the given data
     *
     * @param string $string
     * @return mixed
     */
    protected function decode(string $string)
    {
        return \parse_ini_string($string, true, \INI_SCANNER_NORMAL);
    }

    /**
     * @param mixed $data
     * @return string
     */
    protected function encode($data): string
    {
        return $this->render($data, []);
    }

    /**
     * @param array $data
     * @param array $parent
     * @return string
     */
    protected function render(array $data = [], array $parent = []): string
    {
        $result = [];

        foreach ($data as $dataKey => $dataValue) {
            if (\is_array($dataValue)) {
                if (self::isMulti($dataValue)) {
                    $sections = \array_merge($parent, (array)$dataKey);
                    $result[] = '';
                    $result[] = '[' . \implode('.', $sections) . ']';
                    $result[] = $this->render($dataValue, $sections);
                } else {
                    foreach ($dataValue as $key => $value)
                    {
                        $result[] = $dataKey . '[' . $key . '] = "' . \str_replace('"', '\"', $value) . '"';
                    }
                }
            } else {
                $result[] = $dataKey . ' = "' . \str_replace('"', '\"', $dataValue) . '"';
            }
        }

        return \implode(Factory::LE, $result);
    }
}
