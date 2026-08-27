<?php declare(strict_types=1);

namespace Simtabi\Pheg\Toolbox;

use Closure;
use Exception;
use Simtabi\Pheg\Toolbox\Arr\Arr;
use Simtabi\Enekia\Vanilla\Validators;
use Simtabi\Pheg\Toolbox\Data\Types\Factory;
use Simtabi\Pheg\Toolbox\Data\Types\JSON;
use Simtabi\Pheg\Toolbox\String\Str;

/**
 * Class Filter
 * @SuppressWarnings(PHPMD.TooManyMethods)
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
final class Filter
{

    private Validators $validators;
    private Arr        $arr;
    private Str        $str;

    public function __construct()
    {
        $this->validators = new Validators;
        $this->arr        = new Arr;
        $this->str        = new Str;
    }

    /**
     * Apply custom filter to variable
     *
     * @param mixed          $value
     * @param string|Closure $filters
     * @return mixed
     * @throws Exception
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     * @SuppressWarnings(PHPMD.ShortMethodName)
     */
    public function _($value, $filters = 'raw')
    {
        if (is_string($filters)) {
            $filters = $this->str->trim($filters);
            $filters = explode(',', $filters);

            foreach ($filters as $filter)
            {
                $filterName = $this->cmd($filter);
                if ($filterName)
                {
                    if (method_exists(__CLASS__, $filterName)) {
                        $value = $this->$filterName($value);
                    } else {
                        throw new Exception('Undefined filter method: ' . $filter);
                    }
                }
            }

        } else {
            $value = $filters($value);
        }

        return $value;
    }

    /**
     * Converts many english words that equate to true or false to boolean.
     *
     * @param mixed $variable The string to convert to boolean
     * @return bool
     */
    public function bool($variable): bool
    {
        $yesList = [
            'affirmative',
            'all right',
            'aye',
            'indubitably',
            'most assuredly',
            'ok',
            'of course',
            'oui',
            'okay',
            'sure thing',
            'y',
            'yes',
            'yea',
            'yep',
            'sure',
            'yeah',
            'true',
            't',
            'on',
            '1',
            'vrai',
            'да',
            'д',
            '+',
            '++',
            '+++',
            '++++',
            '+++++',
            '*',
        ];

        $noList  = [
            'no*',
            'no way',
            'nope',
            'nah',
            'na',
            'never',
            'absolutely not',
            'by no means',
            'negative',
            'never ever',
            'false',
            'f',
            'off',
            '0',
            'non',
            'faux',
            'нет',
            'н',
            'немає',
            '-',
            'null',
            'nill',
            'undefined',
        ];

        $variable = $this->str->low($variable);

        if ($this->validators->transfigure()->isInArray($variable, $yesList) || $this->float($variable) !== 0.0) {
            return true;
        }

        if ($this->validators->transfigure()->isInArray($variable, $noList)) {
            return false;
        }

        return filter_var($variable, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Smart converter string to float
     *
     * @param mixed $value
     * @param int   $round
     * @return float
     */
    public function float($value, int $round = 10): float
    {
        $cleaned = (string)preg_replace('#[^\deE\-\.\,]#iu', '', (string)$value);
        $cleaned = str_replace(',', '.', $cleaned);

        preg_match('#[-+]?[\d]+(\.[\d]+)?([eE][-+]?[\d]+)?#', $cleaned, $matches);
        $result = (float)($matches[0] ?? 0.0);

        return round($result, $round);
    }

    /**
     * Smart convert any string to int
     *
     * @param string|float|int|null $value
     * @return int
     */
    public function int($value): int
    {
        $cleaned = (string)preg_replace('#[^0-9-+.,]#', '', (string)$value);
        preg_match('#[-+]?[\d]+#', $cleaned, $matches);
        $result = $matches[0] ?? 0;

        return (int)$result;
    }

    /**
     * Returns only digits chars
     *
     * @param string|null $value
     * @return string
     */
    public function digits(?string $value): string
    {
        // we need to remove - and + because they're allowed in the filter
        $cleaned = str_replace(['-', '+'], '', (string)$value);
        return (string)filter_var($cleaned, FILTER_SANITIZE_NUMBER_INT);
    }

    /**
     * Returns only alpha chars
     *
     * @param string|null $value
     * @return string
     */
    public function alpha(?string $value): string
    {
        return (string)preg_replace('#[^[:alpha:]]#', '', (string)$value);
    }

    /**
     * Returns only alpha and digits chars
     *
     * @param string|null $value
     * @return string
     */
    public function alphanum(?string $value): string
    {
        return (string)preg_replace('#[^[:alnum:]]#', '', (string)$value);
    }

    /**
     * Returns only chars for base64
     *
     * @param string $value
     * @return string
     */
    public function base64(string $value): string
    {
        return (string)preg_replace('#[^A-Z0-9\/+=]#i', '', $value);
    }

    /**
     * Remove whitespaces
     *
     * @param string $value
     * @return string
     */
    public function path(string $value): string
    {
        $pattern = '#^[A-Za-z0-9_\/-]+[A-Za-z0-9_\.-]*([\\\\\/][A-Za-z0-9_-]+[A-Za-z0-9_\.-]*)*$#';
        preg_match($pattern, $value, $matches);
        return $matches[0] ?? '';
    }

    /**
     * Remove whitespaces
     *
     * @param string $value
     * @return string
     */
    public function trim(string $value): string
    {
        return $this->str->trim($value);
    }

    /**
     * Remove whitespaces
     *
     * @param string $value
     * @return string
     */
    public function trimExtend(string $value): string
    {
        return $this->str->trim($value, true);
    }

    /**
     * Cleanup array
     *
     * @param mixed          $value
     * @param Closure|string|null $filter
     * @return array
     */
    public function arr(array $value, Closure|string $filter = null): array
    {
        $array = (array) $value;

        if ($filter === 'noempty') {
            $array = $this->arr->clean($array);
        } elseif ($filter instanceof Closure) {
            $array = array_filter($array, $filter); // TODO add support both - key + value
        }

        return $array;
    }

    /**
     * Cleanup system command
     *
     * @param string $value
     * @return string
     */
    public function cmd(string $value): string
    {
        $value = $this->str->low($value);
        $value = (string)preg_replace('#[^a-z0-9\_\-\.]#', '', $value);
        return $this->str->trim($value);
    }

    /**
     * Get safe string
     *
     * @param string $string
     * @return string
     */
    public function strip(string $string): string
    {
        $cleaned = strip_tags($string);
        return $this->str->trim($cleaned);
    }

    /**
     * Get safe string
     *
     * @param string $string
     * @return string
     */
    public function alias(string $string): string
    {
        $cleaned = $this->strip($string);
        return $this->str->slug($cleaned);
    }

    /**
     * String to lower and trim
     *
     * @param string $string
     * @return string
     */
    public function low(string $string): string
    {
        $cleaned = $this->str->low($string);
        return $this->str->trim($cleaned);
    }

    /**
     * String to upper and trim
     *
     * @param string $string
     * @return string
     *
     * @SuppressWarnings(PHPMD.ShortMethodName)
     */
    public function up(string $string): string
    {
        $cleaned = $this->str->up($string);
        return $this->str->trim($cleaned);
    }

    /**
     * Strip spaces
     *
     * @param string $string
     * @return string
     */
    public function stripSpace(string $string): string
    {
        return $this->str->stripSpace($string);
    }

    /**
     * Alias of "$this->str->clean($string, true, true)"
     *
     * @param string $string
     * @return string
     */
    public function clean(string $string): string
    {
        return $this->str->clean($string, true, true);
    }

    /**
     * Alias of "$this->str->htmlEnt($string)"
     *
     * @param string $string
     * @return string
     */
    public function html(string $string): string
    {
        return $this->str->htmlEnt($string);
    }

    /**
     * Alias of "Xml::escape($string)"
     *
     * @param string $string
     * @return string
     */
    public function xml(string $string): string
    {
        return (new Xml())->escape($string);
    }

    /**
     * Alias of "$this->str->esc($string)"
     *
     * @param string $string
     * @return string
     */
    public function esc(string $string): string
    {
        return $this->str->esc($string);
    }

    /**
     * Returns JSON object from array
     *
     * @param array|Factory $data
     * @return Factory
     */
    public function data($data): Factory
    {
        if ($data instanceof Factory) {
            return $data;
        }

        return new JSON($data);
    }

    /**
     * RAW placeholder
     *
     * @param mixed $string
     * @return mixed
     */
    public function raw($string)
    {
        return $string;
    }

    /**
     * First char to upper, other to lower
     *
     * @param string $input
     * @return string
     */
    public function ucFirst(string $input): string
    {
        $string = $this->str->low($input);
        return ucfirst($string);
    }

    /**
     * Parse lines to assoc list
     *
     * @param string|array $input
     * @return array
     */
    public function parseLines($input): array
    {
        if (is_array($input)) {
            $input = implode(PHP_EOL, $input);
        }

        return $this->str->parseLines($input);
    }

    /**
     * Convert words to PHP Class name
     *
     * @param string $input
     * @return string
     */
    public function className(string $input): string
    {
        $output = (string)preg_replace(['#(?<=[^A-Z\s])([A-Z\s])#i'], ' $0', $input);
        $output = explode(' ', $output);

        $output = array_map(function ($item) {
            $item = (string)preg_replace('#[^a-z0-9]#i', '', $item);
            return Filter::ucFirst($item);
        }, $output);

        return implode('', array_filter($output));
    }

    /**
     * Strip quotes.
     *
     * @param string $value
     * @return string
     */
    public function stripQuotes(string $value): string
    {
        if (str_starts_with($value, '"') && str_ends_with($value, '"')) {
            $value = trim($value, '"');
        }

        if (str_starts_with($value, "'") && str_ends_with($value, "'")) {
            $value = trim($value, "'");
        }

        return $value;
    }

    public function trimIfString($value) {
        return is_string($value) ? trim($value) : $value;
    }
}
