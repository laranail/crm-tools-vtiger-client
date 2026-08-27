<?php declare(strict_types=1);

namespace Simtabi\Pheg\Toolbox\Arr;

use Closure;
use Simtabi\Pheg\Toolbox\Arr\Collection\Collection;
use Simtabi\Pheg\Toolbox\Arr\Query\ArrayQuery;
use Simtabi\Pheg\Toolbox\Arr\Query\QueryEngineHandler;
use Simtabi\Pheg\Toolbox\Transfigures\Transfigure;
use stdClass;

/**
 * Class Arr
 *
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
final class Arr
{

    public function __construct() {}

    public function collection(array $data): Collection
    {
        return new Collection($data);
    }

    public function query(string|array $data): Query\QueryEngine|bool
    {

        if (is_array($data)) {
            // from php array
            $arr = new ArrayQuery();

            return $arr->collect($data);
        }

        if (is_string($data)) {
            // from json file
            return new QueryEngineHandler($data);
        }

        return false;
    }

    public function set($key, $value, array &$data) {
        if ($key === null)
        {
            return null;
        }

        $keys = explode('.', $key);

        while (count($keys) > 1)
        {
            $key = array_shift($keys);

            if ( ! isset($data[$key]) || ! is_array($data[$key]))
            {
                $data[$key] = [];
            }

            $data = &$data[$key];
        }

        $data[array_shift($keys)] = $value;

        return $data;
    }

    /**
     * Get a value from an object or an array.  Allows the ability to fetch a nested value from a
     * heterogeneous multidimensional collection using dot notation.
     *
     * @param array|object $data
     * @param string       $key
     * @param mixed        $default
     * @return mixed
     */
    public function fetch($key, array|object $data, $default = null ) {
        $out = $default;
        if ( is_array( $data ) && array_key_exists( $key, $data ) ) {
            $out = $data[$key];
        } elseif ( is_object( $data ) && property_exists( $data, $key ) ) {
            $out = $data->$key;
        } else {
            $segments = explode( '.', $key );
            foreach ( $segments as $segment ) {
                if ( is_array( $data ) && array_key_exists( $segment, $data ) ) {
                    $out = $data = $data[$segment];
                } elseif ( is_object( $data ) && property_exists( $data, $segment ) ) {
                    $out = $data = $data->$segment;
                } else {
                    $out = $default;
                    break;
                }
            }
        }
        return $out;
    }

    public function fetchRandomFromArray(array $array, int $total = 1)
    {
        // https://www.schmengler-se.de/en/2015/09/efficiently-draw-random-elements-from-large-php-array/
        $totalValues = count($array);
        $total       = min($total, $totalValues);
        $picked      = array_fill(0, $total, 0);
        $backup      = array_fill(0, $total, 0);
        // partially shuffle the array, and generate unbiased selection simultaneously
        // this is a variation on fisher-yates-knuth shuffle
        for ($i = 0; $i < $total; $i++) // O(n) times
        {
            $selected              = mt_rand( 0, --$totalValues ); // unbiased sampling N * N-1 * N-2 * .. * N-n+1
            $value                 = $array[ $selected ];
            $array[ $selected ]    = $array[ $totalValues ];
            $array[ $totalValues ] = $value;
            $backup[ $i ]          = $selected;
            $picked[ $i ]          = $value;
        }
        // restore partially shuffled input array from backup
        // optional step, if needed it can be ignored, e.g $a is passed by value, hence copied
        for ($i = $total - 1; $i >= 0; $i--) // O(n) times
        {
            $selected              = $backup[ $i ];
            $value                 = $array[ $totalValues ];
            $array[ $totalValues ] = $array[ $selected ];
            $array[ $selected ]    = $value;
            $totalValues++;
        }
        return $picked;
    }

    /**
     * Remove the duplicates from an array.
     *
     * @param array $array
     * @param bool  $keepKeys
     * @return array
     */
    public function unique(array $array, bool $keepKeys = false): array
    {
        if ($keepKeys) {
            $array = array_unique($array);
        } else {
            // This is faster version than the builtin array_unique().
            // http://stackoverflow.com/questions/8321620/array-unique-vs-array-flip
            // http://php.net/manual/en/function.array-unique.php
            $array = array_keys(array_flip($array));
        }

        return $array;
    }

    /**
     * Uniques an array based on a given key.
     *
     * @param array<mixed> $input The input array.
     * @param string $key         The key which must appear only once.
     * @return array<mixed>
     */
    public function uniqueArray(array $input, string $key): array
    {
        $output = [];
        $count = 0;
        $temp = [];

        foreach ($input as $value) {
            if (!in_array($value[$key], $temp, true)) {
                $temp[$count] = $value[$key];
                $output[$count] = $value;
            }

            ++$count;
        }

        return $output;
    }

    /**
     * Returns the first element in an array.
     *
     * @param array $array
     * @return mixed
     */
    public function first(array $array)
    {
        return reset($array);
    }

    /**
     * Returns the last element in an array.
     *
     * @param array $array
     * @return mixed
     */
    public function last(array $array)
    {
        return end($array);
    }

    /**
     * Returns the first key in an array.
     *
     * @param array $array
     * @return int|string|null
     */
    public function firstKey(array $array)
    {
        reset($array);
        return key($array);
    }

    /**
     * Returns the last key in an array.
     *
     * @param array $array
     * @return int|string|null
     */
    public function lastKey(array $array)
    {
        end($array);
        return key($array);
    }

    /**
     * Flatten a multi-dimensional array into a one dimensional array.
     *
     * @param array $array        The array to flatten
     * @param bool  $preserveKeys Whether or not to preserve array keys. Keys from deeply nested arrays will
     *                            overwrite keys from shallow nested arrays
     * @return array
     */
    public function flat(array $array, bool $preserveKeys = true): array
    {
        $flattened = [];

        array_walk_recursive(
            $array,
            /**
             * @param mixed      $value
             * @param string|int $key
             */
            static function ($value, $key) use (&$flattened, $preserveKeys): void {
                if ($preserveKeys && !is_int($key)) {
                    $flattened[$key] = $value;
                } else {
                    $flattened[] = $value;
                }
            }
        );

        return $flattened;
    }

    public function filter(array $data)
    {
        if (!is_array($data)){
            return null;
        }
        foreach ($data as $key => $datum){
            if (is_array($datum)){
                $this->filter($datum);
            }elseif ( empty($datum) && strlen($datum) == 0 ) {
                unset($data[$key]);
            }
        }

        return $data;
    }

    /**
     * Searches for a given value in an array of arrays, objects and scalar values. You can optionally specify
     * a field of the nested arrays and objects to search in.
     *
     * @param array       $array  The array to search
     * @param mixed       $search The value to search for
     * @param string|null $field  The field to search in, if not specified all fields will be searched
     * @return bool|mixed  False on failure or the array key on success
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function search(array $array, $search, ?string $field = null)
    {
        // *grumbles* stupid PHP type system
        $search = (string)$search;
        foreach ($array as $key => $element) {
            // *grumbles* stupid PHP type system

            $key = (string)$key;

            if ($field) {
                /** @noinspection NotOptimalIfConditionsInspection */
                if (is_object($element) && $element->{$field} === $search) {
                    return $key;
                }

                /** @noinspection NotOptimalIfConditionsInspection */
                if (is_array($element) && $element[$field] === $search) {
                    return $key;
                }

                /** @noinspection NotOptimalIfConditionsInspection */
                if (is_scalar($element) && $element === $search) {
                    return $key;
                }
            } elseif (is_object($element)) {
                $element = (array)$element;
                if (in_array($search, $element, false)) {
                    return $key;
                }
            } elseif (is_array($element) && in_array($search, $element, false)) {
                return $key;
            } elseif (is_scalar($element) && $element === $search) {
                return $key;
            }
        }

        return false;
    }

    /**
     * Returns an array containing all the elements of arr1 after applying
     * the callback function to each one.
     *
     * @param array    $array      An array to run through the callback function
     * @param callable $callback   Callback function to run for each element in each array
     * @param bool     $onNoScalar Whether or not to call the callback function on non scalar values
     *                             (Objects, resources, etc)
     * @return array
     */
    public function mapDeep(array $array, callable $callback, bool $onNoScalar = false): array
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $args = [$value, $callback, $onNoScalar];
                $array[$key] = call_user_func_array([__CLASS__, __FUNCTION__], $args);
            } elseif (is_scalar($value) || $onNoScalar) {
                $array[$key] = $callback($value);
            }
        }

        return $array;
    }

    /**
     * Clean array by custom rule
     *
     * @param array $haystack
     * @return array
     */
    public function clean(array $haystack): array
    {
        return array_filter($haystack);
    }

    /**
     * Clean array before serialize to JSON
     *
     * @param array $array
     * @return array
     */
    public function cleanBeforeJson(array $array): array
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = $this->cleanBeforeJson($array[$key]);
            }

            if ($array[$key] === '' || null === $array[$key]) {
                unset($array[$key]);
            }
        }

        return $array;
    }

    /**
     * Add cell to the start of assoc array
     *
     * @param array      $array
     * @param string|int $key
     * @param mixed      $value
     * @return array
     */
    public function unshiftAssoc(array &$array, $key, $value): array
    {
        $array = array_reverse($array, true);
        $array[$key] = $value;
        $array = array_reverse($array, true);

        return $array;
    }

    /**
     * Get one field from array of arrays (array of objects)
     *
     * @param array  $arrayList
     * @param string $fieldName
     * @return array
     */
    public function getField(array $arrayList, string $fieldName = 'id'): array
    {
        $result = [];

        foreach ($arrayList as $option) {
            if (is_array($option)) {
                $result[] = $option[$fieldName];
            } elseif (is_object($option)) {
                if (isset($option->{$fieldName})) {
                    $result[] = $option->{$fieldName};
                }
            }
        }

        return $result;
    }

    /**
     * Group array by key
     *
     * @param array  $arrayList
     * @param string $key
     * @return array
     */
    public function groupByKey(array $arrayList, string $key = 'id'): array
    {
        $result = [];

        foreach ($arrayList as $item) {
            if (is_object($item)) {
                if (isset($item->{$key})) {
                    $result[$item->{$key}][] = $item;
                }
            } elseif (is_array($item)) {
                if (array_key_exists($key, $item)) {
                    $result[$item[$key]][] = $item;
                }
            }
        }

        return $result;
    }

    /**
     * Recursive array mapping
     *
     * @param Closure $function
     * @param array   $array
     * @return array
     */
    public function map(Closure $function, array $array): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $result[$key] = $this->map($function, $value);
            } else {
                $result[$key] = $function($value);
            }
        }

        return $result;
    }

    /**
     * Sort an array by keys based on another array
     *
     * @param array $array
     * @param array $orderArray
     * @return array
     */
    public function sortByArray(array $array, array $orderArray): array
    {
        return array_merge(array_flip($orderArray), $array);
    }

    /**
     * Add some prefix to each key
     *
     * @param array  $array
     * @param string $prefix
     * @return array
     */
    public function addEachKey(array $array, string $prefix): array
    {
        $result = [];

        foreach ($array as $key => $item) {
            $result[$prefix . $key] = $item;
        }

        return $result;
    }

    /**
     * Convert assoc array to comment style
     *
     * @param array $data
     * @return string
     */
    public function toComment(array $data): string
    {
        $result = [];
        foreach ($data as $key => $value) {
            $result[] = $key . ': ' . $value . ';';
        }

        return implode(PHP_EOL, $result);
    }

    /**
     * Wraps its argument in an array unless it is already an array
     *
     * @param mixed $object
     * @return array
     * @example
     *   Arr.wrap(null)      # => []
     *   Arr.wrap([1, 2, 3]) # => [1, 2, 3]
     *   Arr.wrap(0)         # => [0]
     *
     */
    public function wrap($object): array
    {
        if (null === $object) {
            return [];
        }

        if (is_array($object) && !$this->isAssocArray($object)) {
            return $object;
        }

        return [$object];
    }

    /**
     * Array imploding for nested array
     *
     * @param string $glue
     * @param array  $array
     * @return string
     */
    public function implode(string $glue, array $array): string
    {
        $result = '';
        $str    = new Str;

        foreach ($array as $item) {
            if (is_array($item)) {
                $result .= $this->implode($glue, $item) . $glue;
            } else {
                $result .= $item . $glue;
            }
        }

        if ($glue) {
            $result = $str->sub($result, 0, 0 - $str->len($glue));
        }

        return $result;
    }

    /**
     * Return an imploded string from a multi dimensional array.
     *
     * @param string $glue
     * @param array  $array
     *
     * @return string
     */
    public function implodeRecursive($glue, array $array)
    {
        $return = '';
        $index  = 0;
        $count  = count($array);
        foreach ($array as $piece) {
            if (is_array($piece)) {
                $return .= $this->implodeRecursive($glue, $piece);
            } else {
                $return .= $piece;
            }
            if ($index < $count - 1) {
                $return .= $glue;
            }
            ++$index;
        }
        return $return;
    }

    /**
     * @param array                      $array
     * @param string|float|int|bool|null $value
     * @return array
     */
    public function removeByValue(array $array, $value): array
    {
        return array_filter(
            $array,
            /**
             * @param string|float|int|bool|null $arrayItem
             */
            static function ($arrayItem) use ($value): bool {
                return $value !== $arrayItem;
            },
            ARRAY_FILTER_USE_BOTH
        );
    }

    /**
     * Sorts multidimensional array by their values.
     *
     * @param array $input The input array to be sorted.
     * @param array $order The sort order. Those values here are the keys inside the input array.
     * @return array $input
     */
    public function usortMulti(array $input, array $order): array
    {
        $inputCloned = $input;

        usort(
            $inputCloned,
            static function($itemA, $itemB) use ($order) {
                $result = [];

                foreach ($order as $value) {

                    $value  = trim($value);
                    $values = explode(' ', $value);
                    $values = array_map('trim', $values);

                    $field  = $values[0] ?? '';
                    $sort   = $values[1] ?? '';

                    if (!isset($itemA[$field], $itemB[$field])) {
                        continue;
                    }

                    if (strcasecmp($sort, 'desc') === 0) {
                        $temp  = $itemA;
                        $itemA = $itemB;
                        $itemB = $temp;
                    }

                    $compare = strcmp($itemA[$field], $itemB[$field]);

                    if (is_numeric($itemA[$field]) && is_numeric($itemB[$field])) {
                        $compare = (float) $itemA[$field] - (float) $itemB[$field];
                    }

                    $result[] = $compare;
                }

                return implode('', $result);
            }
        );

        return $inputCloned;
    }

    public function shuffleAssoc(array $list) {
        if (!is_array($list)) return $list;

        $keys   = array_keys($list);
        shuffle($keys);
        $random = [];
        foreach ($keys as $key) {
            $random[$key] = $list[$key];
        }
        return $random;
    }

    public function randomizeArray(array $array, $maximum = 10){
        $output = [];
        $count  = 0;
        $total  = count($array);

        // shuffle data
        $array = $this->shuffleAssoc($array);

        foreach ($array as $key => $item) {
            if(($maximum <= $total) && ($count < $maximum)){
                $output[$count] = [
                    'key'   => $key,
                    'value' => $item,
                ];
            }
            $count++;
        }
        return $output;
    }

    public function groupBy(array $arr, callable $keySelector) {
        // @author http://codereview.stackexchange.com/questions/23919/generic-array-group-by-using-lambda
        $result = [];
        foreach ($arr as $i) {
            $key = call_user_func($keySelector, $i);
            $result[$key][] = $i;
        }
        return $result;
    }

    public function groupAndReorder($order, $array) {

        /**
        $order_structure = array(
        0 => array(
        0 => 'zippy',
        1 => 'emily',
        2 => 'miles',
        ),

        1 => array(
        0 => 'lisboa',
        1 => 'jim',
        2 => 'fibby',
        ),
        );
         */

        $newOrder = [];
        for($i = 0; $i < count($array); $i++) {
            foreach($order as $category => $user){
                foreach($order[$category] as $key => $username){
                    if((strtolower($array[$i]->username)) == (strtolower($order[$category][$key]))){
                        $newOrder[$category][$key] = $array[$i];
                        continue;
                    }
                }

                // sort the internal structure
                if((!empty($newOrder[$category])) && (is_array($newOrder[$category]))){
                    ksort($newOrder[$category]);
                }

            }
        }

        ksort($newOrder);
        return array_values($newOrder);
    }

    /**
     * Returns the input if it's an array, otherwise false or a custom value.
     *
     * @param mixed $input
     * @param mixed $option
     * @return mixed
     */
    public function getIfIsArray(&$input, $option = false)
    {
        return is_array($input) ? $input : $option;
    }

    /**
     * Returns the input as array.
     *
     * @param mixed $input
     * @return array<mixed>
     */
    public function getArray($input): array
    {
        return is_array($input) ? $input : [$input];
    }

    /**
     * Returns the value of an array based on it's key.
     *
     * @param array<mixed> $input
     * @param mixed $key
     * @param mixed $option
     * @return mixed
     */
    public function getValueIfKeyExists(array $input, $key, $option = false)
    {
        return array_key_exists($key, $input) ? $input[$key] : $option;
    }

    /**
     * Runs a function on an input, no matter if it's a string or an array.
     *
     * @param mixed $input       The input.
     * @param callable $function The function.
     * @return mixed
     */
    public function recurse($input, callable $function)
    {
        if (is_array($input)) {
            return array_map(
                static function($inputPart) use ($function) {
                    return $this->recurse($inputPart, $function);
                },
                $input
            );
        }

        return $function($input);
    }

    public function spliceArray(&$array, $size = 0, $offset = 0){

        // if !array
        if (!is_array($array)){
            return false;
        }

        // count total values in array
        $count = count($array);

        // if size is less or equal to total values,
        // else we will use total count
        // if size is zero, then don't limit the output
        if($size <= $count && ($size !== 0)){
            $count = $size;
        }

        shuffle($array);
        return array_splice($array, $offset, $count);
    }

    public function splitArray($data, $sections = 5){

        $built = [];
        $total = 0;
        $parts = 0;

        if(!empty($data) && is_array($data)){

            $total = count($data);
            if(($sections !== 0 && ($sections < $total)) && (($total !== 0) || ($total !== false))){
                for ( $i = 0; $i < $total; $i++) {
                    if ( !($i % $sections) ) {
                        $parts++;
                    }
                    $built[$parts][] = $data[$i];
                }
            }

        }
        return [
            'parts' => $parts, // last partition count
            'total' => $total, // total data count
            'data'  => $built, // partitioned data
        ];
    }

    public function pushToTop($value, $array){
        if(is_array($array) && !empty($array)){
            // push this error to the very beginning
            array_unshift($array, $value);
        } else{
            $array = $value;
        }

        return $array;
    }

    public function pushToArray($key, $value, $data){
        $parsed = explode('.', $key);
        $array  = &$data;
        while (count($parsed) > 1) {
            $next = array_shift($parsed);
            if ( ! isset($array[$next]) || ! is_array($array[$next])) {
                $array[$next] = [];
            }
            $array =& $array[$next];
        }
        $array[array_shift($parsed)] = $value;
        return $array;
    }

    public function merge(array $new, array $old): array
    {
        if (is_array($old) && (count($old) >= 1)) {
            return array_merge($old, $new);
        }

        return $new;
    }

    public function count($data, $associative = false): int
    {
        return count((new Transfigure())->toArray($data));
    }


    /**
     * Check if key exists
     *
     * @param string|int $key
     * @param array      $array
     * @param bool       $returnValue
     * @return mixed
     * @deprecated Use array_key_exists or ?: or ??
     */
    public function isInArrayKey($key, array $array, bool $returnValue = false): mixed
    {
        $exists = array_key_exists((string)$key, $array);

        if ($returnValue) {
            if ($exists) {
                return $array[$key];
            }
            return null;
        }

        return $exists;
    }

    public function debug($array, $echo = true )
    {
        $output = '<br><pre>' . print_r( $array, true ) . '</pre><br>';
        if($echo){
            echo $output;
        }else{
            return $output;
        }
    }

    public function compareArray($arrayA, $arrayB): bool
    {
        return (
            is_array($arrayA)
            && is_array($arrayB)
            && count($arrayA) == count($arrayB)
            && array_diff($arrayA, $arrayB) === array_diff($arrayB, $arrayA)
        );
    }

    function compareArrayKeyValuesSimilarity(array &$a, array &$b, bool $strict): bool {


        foreach ($a as $key => &$aValue) {

            if (!array_key_exists($key, $b))
                return false;

            $bValue = &$b[$key];


            if (is_array($aValue) && is_array($bValue)) {
                // compare arrays recursively

                if (!$this->compareArrayKeyValuesSimilarity($aValue, $bValue, $strict))
                    return false;
            }
            else if ($strict ? ($aValue !== $bValue) : ($aValue != $bValue)) {
                // use strict comparison if not comparing two arrays

                return false;
            }

        }

        foreach (array_keys($b) as $currKey) {
            if (!array_key_exists($currKey, $a))
                return false;
        }

        return true;
    }

}
