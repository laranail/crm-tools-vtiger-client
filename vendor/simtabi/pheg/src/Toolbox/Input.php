<?php declare(strict_types=1);

namespace Simtabi\Pheg\Toolbox;

final class Input
{

    public function __construct() {}

    /**
     * Returns the variable's value if it's set.
     *
     * @param mixed $input  The variable
     * @param mixed $option What should be returned if the variable is not set
     * @return mixed
     * @deprecated This method has been deprecated. Use PHPs native null coalesce operator.
     */
    public function getIfIsset(&$input, $option = false)
    {
        return $input ?? $option;
    }

    /**
     * Returns the variable if it's not null.
     *
     * @param mixed $input  The variable
     * @param mixed $option What should be returned if the variable is null
     * @return mixed
     * @deprecated This method has been deprecated. Use PHPs native null coalesce operator.
     */
    public function getIfNotNull(&$input, $option = false)
    {
        return $input ?? $option;
    }

    public function returnIf($condition, $value)
    {
        if ($condition) {
            return $value;
        }
        return null;
    }

    public function getCheckboxStatus($name){
        return isset($_REQUEST[$name]) ? ' checked="true" ' : '';
    }

    /**
     * Get checkbox value status
     *
     * @param $input
     * @return bool
     *
     */
    public function isCheckboxStatus($input){
        return !empty($input) || 1 === $input ? 1 : 0;
    }
}
