<?php declare(strict_types=1);

namespace Simtabi\Pheg\Toolbox;

final class Env
{
    public const VAR_NULL   = 1;
    public const VAR_BOOL   = 2;
    public const VAR_INT    = 4;
    public const VAR_FLOAT  = 8;
    public const VAR_STRING = 16;

    public function __construct() {}

    /**
     * Returns an environment variable.
     *
     * @param string $envVarName
     * @param mixed  $default
     * @param int    $options
     * @return mixed
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    public function get(string $envVarName, $default = null, int $options = self::VAR_STRING)
    {
        $envKey = trim($envVarName);
        $value  = getenv($envKey);
        
        if ($value === false) {
            if (array_key_exists($envKey, $_ENV)) {
                return $this->convert($_ENV[$envKey], $options);
            }

            return $default;
        }

        return $this->convert($value, $options);
    }

    /**
     * Converts the type of values like "true", "false", "null" or "123".
     *
     * @param string|null $value
     * @param int         $options
     * @return string|float|int|bool|null
     */
    public function convert(?string $value, int $options = self::VAR_STRING)
    {
        $filter       = new Filter();
        $cleanedValue = trim($filter->stripQuotes((string)$value));

        if ($options & self::VAR_NULL) {
            $cleanedValue = strtolower($cleanedValue);
            if (in_array($cleanedValue, ['null', 'nil', 'undefined'], true)) {
                return null;
            }
        }

        if ($options & self::VAR_STRING) {
            return $cleanedValue;
        }

        if ($options & self::VAR_FLOAT) {
            return $filter->float($cleanedValue);
        }

        if ($options & self::VAR_INT) {
            return $filter->int((int)$cleanedValue);
        }

        if ($options & self::VAR_BOOL) {
            return $filter->bool($cleanedValue);
        }

        return $value;
    }

    /**
     * Convert value of environment variable to clean string
     *
     * @param string $envVarName
     * @param string $default
     * @return string
     */
    public function string(string $envVarName, string $default = ''): string
    {
        if ($this->isExists($envVarName)) {
            return (string)$this->get($envVarName, $default, self::VAR_STRING);
        }

        return $default;
    }

    /**
     * Convert value of environment variable to strict integer value
     *
     * @param string $envVarName
     * @param int    $default
     * @return int
     */
    public function int(string $envVarName, int $default = 0): int
    {
        if ($this->isExists($envVarName)) {
            return (int)$this->get($envVarName, $default, self::VAR_INT);
        }

        return $default;
    }

    /**
     * Convert value of environment variable to strict float value
     *
     * @param string $envVarName
     * @param float  $default
     * @return float
     */
    public function float(string $envVarName, float $default = 0.0): float
    {
        if ($this->isExists($envVarName)) {
            return (float)$this->get($envVarName, $default, self::VAR_FLOAT);
        }

        return $default;
    }

    /**
     * Convert value of environment variable to strict bool value
     *
     * @param string $envVarName
     * @param bool   $default
     * @return bool
     */
    public function bool(string $envVarName, bool $default = false): bool
    {
        if ($this->isExists($envVarName)) {
            return (bool)$this->get($envVarName, $default, self::VAR_BOOL);
        }

        return $default;
    }

    /**
     * Returns true if environment variable exists
     *
     * @param string $envVarName
     * @return bool
     */
    public function isExists(string $envVarName): bool
    {
        return $this->get($envVarName, null, self::VAR_NULL) !== null;
    }
}
