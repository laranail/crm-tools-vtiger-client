<?php declare(strict_types=1);

namespace Simtabi\Pheg\Toolbox\Data\Types;

/**
 * Class PhpArray
 * @package Simtabi\Pheg\Toolbox\Data
 */
final class PhpArray extends Factory
{

    /**
     * Class constructor
     *
     * @param array|string|false|null $data The data array
     */
    public function __construct(array|string|false|null $data = [])
    {
        if ($data && \is_string($data) && \file_exists($data))
        {
            $data = $this->decode($data);
        }

        parent::__construct($data ? (array)$data : []);
    }

    /**
     * Utility Method to unserialize the given data
     *
     * @param string $string
     * @return mixed|null
     */
    protected function decode(string $string)
    {
        if (\file_exists($string)) {
            return include $string;
        }

        return null;
    }

    /**
     * Utility Method to serialize the given data
     *
     * @param mixed $data The data to serialize
     * @return string The serialized data
     */
    protected function encode($data): string
    {
        $data = [
            '<?php',
            '',
            'return ' . \var_export($data, true) . ';',
        ];

        return \implode(self::LE, $data);
    }
}
