<?php declare(strict_types=1);

namespace Simtabi\Pheg\Toolbox\String;

use Exception;
use Simtabi\Pheg\Toolbox\Filter;
use Simtabi\Pheg\Toolbox\Sanitize;
use Simtabi\Pheg\Toolbox\String\Compare\Compare;
use Simtabi\Pheg\Toolbox\System;
use function mb_strtolower;
use Html2Text\Html2Text;
use Cocur\Slugify\Slugify;
use Cocur\Slugify\RuleProvider\RuleProviderInterface;
use Stringy\Stringy as S;
use Illuminate\Support\Str as LStr;
use Simtabi\Pheg\Toolbox\Arr\Arr;

/**
 * Class Str
 *
 * @package Simtabi\Pheg\Toolbox
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 * @SuppressWarnings(PHPMD.TooManyMethods)
 */
final class Str
{

    /**
     * Default charset is UTF-8
     *
     * @var string
     */
    public static $encoding = 'UTF-8';

    private Arr $arr;

    public function __construct()
    {
        $this->arr = new Arr;
    }

    public function wordCountUtf8($sentence)
    {
        return count(preg_split('~[^\p{L}\p{N}\']+~u', strip_tags($sentence)));
    }

    /**
     * Strip all whitespaces from the given string.
     *
     * @param string $string The string to strip
     * @return string
     */
    public function stripSpace(string $string): string
    {
        return (string)preg_replace('/\s+/', '', $string);
    }

    public function stripRepeatingCharOnTheRight($word, $char = ',')
    {
        return preg_replace("/$char+/", $char, rtrim($word, $char));
    }

    public function stripRepeatingChars($word)
    {
        return preg_replace('{(.)\1+}', '$1', $word);
    }

    public function stripSpacesAndWhiteSpaces($str, $stripSpecials = true)
    {

        // remove spaces
        $str = str_replace(' ', '', $str);

        // remove white spaces
        $str = preg_replace('/\s+/', '', $str);

        return true === $stripSpecials ? $this->stripSpecialChars($str) : $str;
    }

    public function stripSpecialChars($text)
    {
        return preg_replace("/[^A-Za-z0-9 -]/", '', $text);
    }

    public static function removeSpecialCharacters(?string $string): array|string|null
    {
        $string = str_replace(' ', '-', $string); // Replaces all spaces with hyphens.
        $string = preg_replace('/[^A-Za-z0-9\-]/', '', $string); // Removes special chars.

        return preg_replace('/-+/', '-', $string); // Replaces multiple hyphens with single one.
    }

    public function stripSEONeat($str, $total = 5, $delimiter = '...')
    {
        // http://monchito.com/blog/regex-php-snippets-for-seo-purposes
        $len = strlen($str);
        if ($len > $total) {
            preg_match('/(.{' . $total . '}.*?)\b/', $str, $matches);
            return rtrim($matches[1]) . $delimiter;
        } else {
            return $str;
        }
    }

    /**
     * Parse text by lines
     *
     * @param string $text
     * @param bool   $toAssoc
     * @return array
     */
    public function parseLines(string $text, bool $toAssoc = true): array
    {
        $text  = htmlspecialchars_decode($text);
        $text  = $this->clean($text, false, false, false);

        $text  = str_replace(["\n", "\r", "\r\n", PHP_EOL], "\n", $text);
        $lines = explode("\n", $text);

        $result = [];
        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            if ($toAssoc) {
                $result[$line] = $line;
            } else {
                $result[] = $line;
            }
        }

        return $result;
    }

    /**
     * Make string safe
     * - Remove UTF-8 chars
     * - Remove all tags
     * - Trim
     * - Add Slashes (opt)
     * - To lower (opt)
     *
     * @param string $string
     * @param bool   $toLower
     * @param bool   $addSlashes
     * @param bool   $removeAccents
     * @return string
     */
    public function clean(
        string $string,
        bool $toLower = false,
        bool $addSlashes = false,
        bool $removeAccents = true
    ): string {
        if ($removeAccents) {
            $string = $this->removeAccents($string);
        }

        $string = strip_tags($string);
        $string = trim($string);

        if ($addSlashes) {
            $string = addslashes($string);
        }

        if ($toLower) {
            $string = $this->low($string);
        }

        return $string;
    }

    /**
     * Convert >, <, ', " and & to html entities, but preserves entities that are already encoded.
     *
     * @param string $string The text to be converted
     * @param bool   $encodedEntities
     * @return string
     */
    public function htmlEnt(string $string, bool $encodedEntities = false): string
    {
        if ($encodedEntities) {
            $transTable = get_html_translation_table(HTML_ENTITIES, ENT_QUOTES, self::$encoding);

            $transTable[chr(38)] = '&';

            $regExp     = '/&(?![A-Za-z]{0,4}\w{2,3};|#[\d]{2,3};)/';

            return (string)preg_replace($regExp, '&amp;', strtr($string, $transTable));
        }

        return htmlentities($string, ENT_QUOTES, self::$encoding);
    }

    /**
     * Get unique string
     *
     * @param string $prefix
     * @return string
     * @throws Exception
     */
    public function unique(string $prefix = 'unique'): string
    {
        $prefix = rtrim(trim($prefix), '-');
        $random = random_int(10000000, 99999999);

        $result = $random;
        if ($prefix) {
            $result = $prefix . '-' . $random;
        }

        return (string)$result;
    }

    /**
     * Generate readable random string
     *
     * @param int $length
     * @param bool $isReadable
     * @return string
     * @throws Exception
     */
    public function random(int $length = 10, bool $isReadable = true): string
    {
        $result = '';

        if ($isReadable) {
            $vowels     = ['a', 'e', 'i', 'o', 'u', '0'];
            $consonants = [
                'b',
                'c',
                'd',
                'f',
                'g',
                'h',
                'j',
                'k',
                'l',
                'm',
                'n',
                'p',
                'r',
                's',
                't',
                'v',
                'w',
                'x',
                'y',
                'z',
                '1',
                '2',
                '3',
                '4',
                '5',
                '6',
                '7',
                '8',
                '9',
            ];

            $max = $length / 2;

            for ($pos = 1; $pos <= $max; $pos++) {
                $result .= $consonants[random_int(0, count($consonants) - 1)];
                $result .= $vowels[random_int(0, count($vowels) - 1)];
            }
        } else {
            $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';

            for ($pos = 0; $pos < $length; $pos++) {
                $result .= $chars[mt_rand() % strlen($chars)];
            }
        }

        return $result;
    }

    /**
     * Pads a given string with zeroes on the left.
     *
     * @param string $number The number to pad
     * @param int    $length The total length of the desired string
     * @return string
     */
    public function zeroPad(string $number, int $length): string
    {
        return str_pad($number, $length, '0', STR_PAD_LEFT);
    }

    public function summarize($text, $length = 400, $append = '...', $splitOnWholeWords = true)
    {
        // https://www.drupal.org/node/46415
        if (strlen($text) <= $length) return $text;
        $split = 0;
        if ($splitOnWholeWords) {
            $i = 0;
            $lplus1 = $length + 1;
            while (($i = strpos($text, ' ', $i + 1)) < $lplus1) {
                if ($i === false) break;
                $split = $i;
            }
        } else {
            $split = $length;
        }

        return substr($text, 0, $split) . $append;
    }

    /**
     * Limit the length of a text string to the nearest word ending.
     *
     * @param  string $string
     * @param  int    $max_length
     * @return string
     */
    public function truncate(string $string, int $max_length = 150): string
    {
        if (strlen($string) > $max_length) {
            $limit = $max_length ?? -1;
            $parts = preg_split('/([\s\n\r]+)/u', $string, $limit, PREG_SPLIT_DELIM_CAPTURE);
            $parts_count = count($parts);
            $length = 0;
            $last_part = 0;
            for (; $last_part < $parts_count; ++$last_part) {
                $length += strlen($parts[$last_part]);
                if ($length > $max_length) {
                    break;
                }
            }
            return implode(array_slice($parts, 0, $last_part));
        } else {
            return $string;
        }
    }

    /**
     * Truncate a string to a specified length without cutting a word off.
     *
     * @param string $string The string to truncate
     * @param int    $length The length to truncate the string to
     * @param string $append Text to append to the string IF it gets truncated, defaults to '...'
     * @return  string
     */
    public function truncateSafe(string $string, int $length, string $append = '...'): string
    {
        $result = $this->sub($string, 0, $length);
        $lastSpace = $this->rPos($result, ' ');

        if ($lastSpace !== null && $string !== $result) {
            $result = $this->sub($result, 0, $lastSpace);
        }

        if ($result !== $string) {
            $result .= $append;
        }

        return $result;
    }

    /**
     * Truncate the string to given length of characters.
     *
     * @param string $string The variable to truncate
     * @param int    $limit  The length to truncate the string to
     * @param string $append Text to append to the string IF it gets truncated, defaults to '...'
     * @return string
     */
    public function limitChars(string $string, int $limit = 100, string $append = '...'): string
    {
        if ($this->len($string) <= $limit) {
            return $string;
        }

        return rtrim($this->sub($string, 0, $limit)) . $append;
    }

    /**
     * Truncate the string to given length of words.
     *
     * @param string $string
     * @param int    $limit
     * @param string $append
     * @return string
     */
    public function limitWords(string $string, int $limit = 100, string $append = '...'): string
    {
        preg_match('/^\s*+(?:\S++\s*+){1,' . $limit . '}/u', $string, $matches);

        if (!array_key_exists('0', $matches) || $this->len($string) === $this->len($matches[0])) {
            return $string;
        }

        return rtrim($matches[0]) . $append;
    }

    /**
     * Check if a given string matches a given pattern.
     *
     * @param string $pattern  Pattern of string expected
     * @param string $haystack String that need to be matched
     * @param bool   $caseSensitive
     * @return bool
     */
    public function like(string $pattern, string $haystack, bool $caseSensitive = true): bool
    {
        if ($pattern === $haystack) {
            return true;
        }

        // Preg flags
        $flags = $caseSensitive ? '' : 'i';

        // Escape any regex special characters
        $pattern = preg_quote($pattern, '#');

        // Unescaped * which is our wildcard character and change it to .*
        $pattern = str_replace('\*', '.*', $pattern);

        return (bool)preg_match('#^' . $pattern . '$#' . $flags, $haystack);
    }

    /**
     * Check is mbstring overload standard functions
     * @return bool
     */
    private function isOverload(): bool
    {
        if (defined('MB_OVERLOAD_STRING') && $this->isMBString()) {
            return (bool)((new Filter())->int((new System())->iniGet('mbstring.func_overload')) & MB_OVERLOAD_STRING);
        }

        return false;
    }

    /**
     * Check is mbstring loaded
     *
     * @return bool
     */
    public function isMBString(): bool
    {
        static $isLoaded;

        if (null === $isLoaded) {
            $isLoaded = extension_loaded('mbstring');

            if ($isLoaded) {
                mb_internal_encoding(self::$encoding);
            }
        }

        return $isLoaded;
    }

    /**
     * Get string length
     *
     * @param string $string
     * @return int
     */
    public function len(string $string): int
    {
        if ($this->isMBString()) {
            return mb_strlen($string, self::$encoding) ?: 0;
        }

        return strlen($string);
    }

    /**
     * Find position of first occurrence of string in a string
     *
     * @param string $haystack
     * @param string $needle
     * @param int    $offset
     * @return int|null
     */
    public function pos(string $haystack, string $needle, int $offset = 0): ?int
    {
        $result = strpos($haystack, $needle, $offset);
        if ($this->isMBString()) {
            $result = mb_strpos($haystack, $needle, $offset, self::$encoding);
        }

        return $result === false ? null : $result;
    }

    /**
     * Find position of last occurrence of a string in a string
     *
     * @param string $haystack
     * @param string $needle
     * @param int    $offset
     * @return int|null
     */
    public function rPos(string $haystack, string $needle, int $offset = 0): ?int
    {
        $result = strrpos($haystack, $needle, $offset);
        if ($this->isMBString()) {
            $result = mb_strrpos($haystack, $needle, $offset, self::$encoding);
        }

        return $result === false ? null : $result;
    }

    /**
     * Finds position of first occurrence of a string within another, case insensitive
     *
     * @param string $haystack
     * @param string $needle
     * @param int    $offset
     * @return int|null
     */
    public function iPos(string $haystack, string $needle, int $offset = 0): ?int
    {
        $result = (int)stripos($haystack, $needle, $offset);
        if ($this->isMBString()) {
            $result = mb_stripos($haystack, $needle, $offset, self::$encoding);
        }

        return $result === false ? null : $result;
    }

    /**
     * Finds first occurrence of a string within another
     *
     * @param string $haystack
     * @param string $needle
     * @param bool   $beforeNeedle
     * @return string
     */
    public function strStr(string $haystack, string $needle, bool $beforeNeedle = false): string
    {
        if ($this->isMBString()) {
            return (string)mb_strstr($haystack, $needle, $beforeNeedle, self::$encoding);
        }

        return (string)strstr($haystack, $needle, $beforeNeedle);
    }

    /**
     * Finds first occurrence of a string within another, case insensitive
     *
     * @param string $haystack
     * @param string $needle
     * @param bool   $beforeNeedle
     * @return string
     */
    public function iStr(string $haystack, string $needle, bool $beforeNeedle = false): string
    {
        if ($this->isMBString()) {
            return (string)mb_stristr($haystack, $needle, $beforeNeedle, self::$encoding);
        }

        return (string)stristr($haystack, $needle, $beforeNeedle);
    }

    /**
     * Finds the last occurrence of a character in a string within another
     *
     * @param string $haystack
     * @param string $needle
     * @param bool   $part
     * @return string
     */
    public function rChr(string $haystack, string $needle, bool $part = false): string
    {
        if ($this->isMBString()) {
            return (string)mb_strrchr($haystack, $needle, $part, self::$encoding);
        }

        return (string)strrchr($haystack, $needle);
    }

    /**
     * Get part of string
     *
     * @param string $string
     * @param int    $start
     * @param int    $length
     * @return string
     */
    public function sub(string $string, int $start, int $length = 0): string
    {
        if ($this->isMBString()) {
            if (0 === $length) {
                $length = $this->len($string);
            }

            return mb_substr($string, $start, $length, self::$encoding) ?: '';
        }

        return (string)substr($string, $start, $length);
    }

    /**
     * Count the number of substring occurrences
     *
     * @param string $haystack
     * @param string $needle
     * @return int
     */
    public function subCount(string $haystack, string $needle): int
    {
        if ($this->isMBString()) {
            return mb_substr_count($haystack, $needle, self::$encoding) ?: 0;
        }

        return substr_count($haystack, $needle);
    }

    /**
     * Converts the first character to upper case.
     *
     * @param mixed $input          The input value.
     * @param string|null $encoding The encoding, for example `UTF-8`.
     *                              If not set, `mb_internal_encoding()` will be called.
     *                              Fallback is `UTF-8`.
     * @return mixed
     */
    public function mbUcFirst($input, string $encoding = null)
    {

        $encodingNew = $encoding ?? mb_internal_encoding();

        if (false === $encodingNew) {
            $encodingNew = self::$encoding;
        }

        return $this->arr->recurse(
            $input,
            function($input) use ($encodingNew) {
                if (!is_string($input)) {
                    return $input;
                }

                $firstCharacter  = mb_substr($input, 0, 1);
                $firstCharacter  = mb_strtoupper($firstCharacter, $encodingNew);
                $otherCharacters = mb_substr($input, 1);
                return $firstCharacter.$otherCharacters;
            }
        );
    }

    /**
     * Make a string lowercase
     *
     * @param string|float|int|bool|null $string
     * @return string
     */
    public function low($string): string
    {
        if ($this->isMBString()) {
            return mb_strtolower((string)$string, self::$encoding) ?: '';
        }

        return strtolower((string)$string);
    }

    /**
     * Make a string uppercase
     *
     * @param string|float|int|bool|null $string
     * @return string
     *
     * @SuppressWarnings(PHPMD.ShortMethodName)
     */
    public function up($string): string
    {
        if ($this->isMBString()) {
            return mb_strtoupper((string)$string, self::$encoding) ?: '';
        }

        return strtoupper((string)$string);
    }


    /**
     * Convert a string to Kebab Case.
     *
     * @param $string
     * @return string
     */
    public function kebabCase($string): string
    {
        // Replace invalid characters with a space.
        $string = (new Sanitize())->filterString($string);
        $string = trim($string);
        $string = strtolower($string);
        return str_replace(' ', '-', $string);
    }

    /**
     * Convert a string to Camel Case.
     *
     * @param $string
     * @return string
     */
    public function camelCase($string): string
    {
        $string = $this->kebabCase($string);
        // Replace separator with spaces.
        $string = str_replace('-', ' ', $string);
        // uppercase the first character of each word
        $string = ucwords($string);
        $string = str_replace(' ', '', $string);
        return lcfirst($string);
    }

    /**
     * Convert a string to Title Case.
     *
     * @param $string
     * @return string
     */
    public function titleCase($string): string
    {
        return ucfirst($this->camelCase($string));
    }

    /**
     * Convert a string to Snake Case.
     *
     * @param $string
     * @return string
     */
    public function snakeCase($string)
    {
        return str_replace('-', '_', $this->kebabCase($string));
    }

    public function sentenceCase($text)
    {
        $text = preg_split('/([.?!]+)/', $text, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
        $out = '';
        foreach ($text as $key => $sentence) {
            $out .= ($key & 1) == 0 ? ucfirst(strtolower(trim($sentence))) : $sentence . ' ';
        }
        return trim($out);
    }

    public function sentenceCap($text, $breakpoint = '.')
    {
        $text = explode($breakpoint, $text);
        $out = array();
        foreach ($text as $sentence) {
            $out[] = ucfirst(strtolower($sentence));
        }
        return implode($breakpoint, $out);
    }

    public function fromCamelCase($string)
    {
        $pattern = '!([A-Z][A-Z0-9]*(?=$|[A-Z][a-z0-9])|[A-Za-z][a-z0-9]+)!';
        preg_match_all($pattern, $string, $matches);
        $ret = $matches[0];
        foreach ($ret as &$match) {
            $match = $match == strtoupper($match) ?
                strtolower($match) :
                lcfirst($match);
        }
        return implode('_', $ret);
    }

    public function dropCap($string)
    {
        return preg_replace('/^([\<\sa-z\d\/\>]*)(([a-z\&\;]+)|([\"\'\w]))/', '$1<b>$2</b>', $string);
    }

    public function firstSentence($content)
    {
        $content = html_entity_decode(strip_tags($content));
        $pos = strpos($content, '.');
        if ($pos === false) {
            return $content;
        } else {
            return substr($content, 0, $pos + 1);
        }
    }

    /**
     * Trim whitespaces and other special chars
     *
     * @param string $value
     * @param bool   $extendMode
     * @return string
     */
    public function trim(string $value, bool $extendMode = false): string
    {
        $result = trim($value);

        if ($extendMode) {
            $result = trim($result, chr(0xE3) . chr(0x80) . chr(0x80));
            $result = trim($result, chr(0xC2) . chr(0xA0));
            $result = trim($result);
        }

        return $result;
    }

    /**
     * Escape string before save it as xml content.
     * The function is moved. Please, use \Simtabi\Pheg\Toolbox\Xml::escape($string). It'll be deprecated soon.
     *
     * @param string $string
     * @return string
     * @deprecated
     */
    public function escXml(string $string): string
    {
        return (new Xml())->escape($string);
    }

    /**
     * Escape UTF-8 strings
     *
     * @param string $string
     * @return string
     */
    public function esc(string $string): string
    {
        return htmlspecialchars($string, ENT_NOQUOTES, self::$encoding);
    }

    /**
     * Convert camel case to human readable format
     *
     * @param string $input
     * @param string $separator
     * @param bool   $toLower
     * @return string
     */
    public function splitCamelCase(string $input, string $separator = '_', bool $toLower = true): string
    {
        $original = $input;

        $output = (string)preg_replace(['/(?<=[^A-Z])([A-Z])/', '/(?<=[^0-9])([0-9])/'], '_$0', $input);
        $output = (string)preg_replace('#_{1,}#', $separator, $output);

        $output = trim($output);
        if ($toLower) {
            $output = strtolower($output);
        }

        if ('' === $output) {
            return $original;
        }

        return $output;
    }

    /**
     * Convert test name to human readable string
     *
     * @param string $input
     * @return string
     */
    public function testName2Human(string $input): string
    {
        $original = $input;
        $input    = $this->getClassName($input);

        /** @noinspection NotOptimalRegularExpressionsInspection */
        if (!preg_match('#^tests#i', $input)) {
            $input = (string)preg_replace('#^(test)#i', '', $input);
        }

        $input = (string)preg_replace('#(test)$#i', '', $input);
        $output = (string)preg_replace(['/(?<=[^A-Z])([A-Z])/', '/(?<=[^0-9])([0-9])/'], ' $0', $input);
        $output = str_replace('_', ' ', $output);
        $output = trim($output);

        $output = implode(' ', array_filter(array_map(function (string $item): string {
            $item = ucwords($item);
            $item = trim($item);
            return $item;
        }, explode(' ', $output))));


        if (strcasecmp($original, $output) === 0) {
            return $original;
        }

        if ('' === $output) {
            return $original;
        }

        return $output;
    }

    /**
     * Generates a universally unique identifier (UUID v4) according to RFC 4122
     * Version 4 UUIDs are pseudo-random!
     *
     * Returns Version 4 UUID format: xxxxxxxx-xxxx-4xxx-Yxxx-xxxxxxxxxxxx where x is
     * any random hex digit and Y is a random choice from 8, 9, a, or b.
     *
     * @see http://stackoverflow.com/questions/2040240/php-function-to-generate-v4-uuid
     *
     * @return string
     * @throws Exception
     */
    public function uuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            // 32 bits for "time_low"
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            // 16 bits for "time_mid"
            random_int(0, 0xffff),
            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            random_int(0, 0x0fff) | 0x4000,
            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            random_int(0, 0x3fff) | 0x8000,
            // 48 bits for "node"
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0xffff)
        );
    }

    /**
     * Get class name without namespace
     *
     * @param mixed $object
     * @param bool  $toLower
     * @return string
     */
    public function getClassName($object, bool $toLower = false): string
    {
        if (!$object) {
            return '';
        }

        $className = $object;
        if (is_object($object)) {
            $className = get_class($object);
        }

        $result = $className;
        if (strpos($className, '\\') !== false) {
            $className = explode('\\', $className);
            reset($className);
            $result = end($className);
        }

        if ($toLower) {
            $result = strtolower($result);
        }

        return $result;
    }

    /**
     * Increments a trailing number in a string.
     * Used to easily create distinct labels when copying objects. The method has the following styles:
     *  - default: "Label" becomes "Label (2)"
     *  - dash:    "Label" becomes "Label-2"
     *
     * @param string $string The source string.
     * @param string $style  The the style (default|dash).
     * @param int    $next   If supplied, this number is used for the copy, otherwise it is the 'next' number.
     * @return string
     */
    public function inc(string $string, string $style = 'default', int $next = 0): string
    {
        $styles = [
            'dash'    => ['#-(\d+)$#', '-%d'],
            'default' => [['#\((\d+)\)$#', '#\(\d+\)$#'], [' (%d)', '(%d)']],
        ];

        $styleSpec = $styles[$style] ?? $styles['default'];

        // Regular expression search and replace patterns.
        if (is_array($styleSpec[0])) {
            $rxSearch = $styleSpec[0][0];
            /** @noinspection MultiAssignmentUsageInspection */
            $rxReplace = $styleSpec[0][1];
        } else {
            $rxSearch = $rxReplace = $styleSpec[0];
        }

        // New and old (existing) sprintf formats.
        if (is_array($styleSpec[1])) {
            $newFormat = $styleSpec[1][0];
            /** @noinspection MultiAssignmentUsageInspection */
            $oldFormat = $styleSpec[1][1];
        } else {
            $newFormat = $oldFormat = $styleSpec[1];
        }

        // Check if we are incrementing an existing pattern, or appending a new one.
        if (preg_match($rxSearch, $string, $matches)) {
            $next = empty($next) ? ((int)$matches[1] + 1) : $next;
            $string = (string)preg_replace($rxReplace, sprintf($oldFormat, $next), $string);
        } else {
            $next = empty($next) ? 2 : $next;
            $string .= sprintf($newFormat, $next);
        }

        return $string;
    }

    /**
     * Convert array of strings to list as pretty print description
     * @param array $data
     * @param bool  $alignByKeys
     * @return string|null
     */
    public function listToDescription(array $data, bool $alignByKeys = false): ?string
    {
        /** @psalm-suppress MissingClosureParamType */
        $maxWidth = array_reduce(array_keys($data), function ($acc, $key) use ($data): int {
            if ('' === trim((string)$data[$key])) {
                return $acc;
            }

            if ($acc < strlen((string)$key)) {
                $acc = strlen((string)$key);
            }

            return $acc;
        }, 0);

        $result = [];
        foreach ($data as $key => $value) {
            $value = trim((string)$value);
            $key = trim((string)$key);

            if ('' !== $value) {
                $keyFormated = $key;
                if ($alignByKeys) {
                    $keyFormated = str_pad($key, $maxWidth, ' ', STR_PAD_RIGHT);
                }

                if (is_numeric($key) || $key === '') {
                    $result[] = $value;
                } else {
                    $result[] = ucfirst($keyFormated) . ': ' . $value;
                }
            }
        }

        if (count($result) === 0) {
            return null;
        }

        return implode("\n", $result) . "\n";
    }

    /**
     * Converts the input to boolean if possible.
     *
     * @param mixed $input
     * @return mixed
     */
    public function stringToBoolean($input)
    {
        return $this->arr->recurse(
            $input,
            function($input) {
                $inputConverted = $input;

                if (is_string($input)) {
                    $inputConverted = mb_strtolower($input);
                }

                return match ($inputConverted) {
                    'true'  => true,
                    'false' => false,
                    'null'  => null,
                    default => $input,
                };

            }
        );
    }

    /**
     * Converts the input to boolean if possible and handles also `yes` and `no`.
     *
     * @param mixed $input
     * @return mixed
     */
    public function stringToBooleanAdvanced($input)
    {
        $standard = $this->stringToBoolean($input);

        if (is_bool($standard) || null === $standard) {
            return $standard;
        }

        return $this->arr->recurse($standard,
            function($input) {
                $inputConverted = $input;

                if (is_string($input)) {
                    $inputConverted = mb_strtolower($input);
                }

                return match ($inputConverted) {
                    'yes'   => true,
                    'no'    => false,
                    default => $input,
                };

            }
        );
    }

    /**
     * Converts the input to int if possible.
     *
     * @param mixed $input
     * @return mixed
     */
    public function stringToInt($input)
    {
        return $this->arr->recurse(
            $input,
            function($input) {

                if (is_bool($input) || null === $input) {
                    return $input;
                }

                $number     = (int) $input;
                $sameLength = strlen($input) === strlen((string) $number);

                if ($sameLength) {
                    return $number;
                }

                return $input;
            }
        );
    }

    /**
     * Converts the input to float if possible.
     *
     * @param mixed $input
     * @return mixed
     */
    public function stringToFloat($input)
    {
        return $this->arr->recurse(
            $input,
            function($input) {

                if (is_bool($input) || null === $input) {
                    return $input;
                }

                $number     = (float) $input;
                $sameLength = strlen($input) === strlen((string) $number);

                if ($sameLength) {
                    return $number;
                }

                return $input;
            }
        );
    }

    /**
     * Converts the input to int or float if possible.
     *
     * @param mixed $input
     * @return mixed
     */
    public function stringToNumber($input)
    {
        return $this->arr->recurse(
            $input,
            function($input) {

                if (is_bool($input) || null === $input) {
                    return $input;
                }

                $int = $this->stringToInt($input);

                if (is_int($int)) {
                    return $int;
                }

                $float = $this->stringToFloat($input);

                if (is_float($float)) {
                    return $float;
                }

                return $input;
            }
        );
    }

    /**
     * Converts the input to string.
     *
     * @param mixed $input
     * @return mixed
     */
    public function booleanToString($input): mixed
    {
        return $this->arr->recurse($input, function($input) {
            if ($input === true) {
                return 'true';
            }

            if ($input === false) {
                return 'false';
            }

            if ($input === null) {
                return 'null';
            }

            return $input;
        });
    }

    public function naturalLanguageJoin(array $list, $conjunction = 'and')
    {

        // Join a string with a natural language conjunction at the end.
        //https://gist.github.com/angry-dan/e01b8712d6538510dd9c

        // option 1
        $last  = array_slice($list, -1);
        $first = join(', ', array_slice($list, 0, -1));
        $both  = array_filter(array_merge(array($first), $last), 'strlen');
        return join(" $conjunction ", $both);

        // option 2
        $last = array_pop($list);
        if ($list) {
            return implode(', ', $list) . ' ' . $conjunction . ' ' . $last;
        }
        return $last;

    }

    /**
     * Replaces values in multidimensional arrays.
     *
     * @param mixed $search  The needle
     * @param mixed $replace The replacement
     * @param array $subject The input array
     * @return array
     */
    public function strReplaceMulti($search, $replace, array $subject): array
    {
        $subjectEncoded  = json_encode($subject);
        $subjectReplaced = str_replace($search, $replace, (string) $subjectEncoded);
        return json_decode($subjectReplaced, true);
    }

    /**
     * Function multipleExplode
     *
     *
     * @param $string - has to be a Str
     * @param array $delimiters - has to be an Array
     * @return mixed
     *
     */
    public function multipleExplode($string, $delimiters = ',:-_')
    {
        return preg_split("/[$delimiters]/", $string);
    }

    public function addCharIf($count, $word, $char, $space = false)
    {
        return $count . (true === $space ? " " : '') . $word . ($count >= 2 ? $char : ($count == 0 ? $char : ''));
    }

    public function makeItReadable($str, $type = 1)
    {
        return match ($type) {
            1       => ucfirst(strtolower(str_replace('_', ' ', $str))),
            2       => strtolower(str_replace('_', ' ', $str)),
            3       => strtoupper(str_replace('_', ' ', $str)),
            4       => function() use($str) {
                $words = '';
                foreach ($this->multipleExplode($str, '-_') as $j) {
                    $words .= ucfirst($j) . ' ';
                }
                return trim($words);
            },
            default => ucwords(strtolower(str_replace('_', ' ', $str))),
        };
    }

    public function generateString($length = 12, $addNumbers = true, $addSpecialChars = true, $addExtraSpecialChars = false, $pattern = null)
    {

        $seed = empty($pattern) ? 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ' : $pattern;
        if ($addNumbers)
            $seed .= '0123456789';
        if ($addSpecialChars)
            $seed .= '!@#$%^&*()';
        if ($addExtraSpecialChars)
            $seed .= '-_ []{}<>~`+=,.;:/?|';

        $seed = str_split($seed);
        $word = '';

        for ($i = 0; $i < $length; $i++) {
            $word .= $seed[array_rand($seed)];
        }

        return $this->stripSpacesAndWhiteSpaces($word);
    }

    public function generateWord($length = 8)
    {

        // output variable
        $output = "";

        // build alphabets list
        $alphabets = [
            // all constants into an array
            'consonant' => ["b", "c", "d", "f", "g", "h", "j", "k", "l", "m", "n", "p", "r", "s", "t", "v", "w", "x", "y", "z"],
            // all vowels into an array
            'vowels'    => ["a", "e", "i", "o", "u"],
        ];

        //start with a consonant or array (0 = consonant, 1 = vowel)
        $start = rand(0, 1);

        // add a consonant and a vowel until the length of the string has been met
        for ($i = 1; $i <= ceil($length / 2); $i++) {

            // if we are to start with a consonant (0==start)
            if ($start == 0) {
                $output .= $alphabets['consonant'][rand(0, 19)];
                $output .= $alphabets['vowels'][rand(0, 4)];
            } else {
                $output .= $alphabets['vowels'][rand(0, 4)];
                $output .= $alphabets['consonant'][rand(0, 19)];
            }

        }

        // return output
        return $output;
    }

    /**
     * Make initials from a word with no spaces
     *
     * @param string $string
     * @return string
     */
    public function generateInitials(string $string): string
    {

        $generate = function ($string){
            preg_match_all('#([A-Z]+)#', $string, $capitals);
            if (count($capitals[1]) >= 2) {
                return substr(implode('', $capitals[1]), 0, 2);
            }
            return strtoupper(substr($string, 0, 2));
        };

        if (empty($string)) {
            return '';
        }

        $words = explode(' ', $string);
        if (count($words) >= 2) {
            return strtoupper(substr($words[0], 0, 1) . substr(end($words), 0, 1));
        }
        return $generate($string);
    }

    public function readingTime(string $story, $spacing = null, $shortForm = false)
    {

        // escape
        $story = trim(htmlspecialchars($story, ENT_QUOTES, 'UTF-8'));

        // set variables
        $wordCount = $this->wordCountUtf8($story);
        $minutes   = floor($wordCount / 120);
        $seconds   = floor($wordCount % 120 / (120 / 60));

        $minStr    = $shortForm ? 'min' : 'minute';
        $secStr    = $shortForm ? 'sec' : 'second';
        $varStr    = 's';

        $spacing   = (!empty($spacing) ? $spacing : ' ');
        $minVar    = (($minutes == 1) ? false : true);
        $secVar    = (($seconds == 1) ? false : true);

        if (1 <= $minutes) {
            $readingMins = $minutes . $spacing . ucwords(strtolower($minStr . ((true === $minVar ? $varStr : null))));
            $readingSecs = $seconds . $spacing . ucwords(strtolower($secStr . ((true === $secVar ? $varStr : null))));
        } else {
            $readingMins = $minutes . $spacing . ucwords(strtolower($minStr . "'" . ((true === $minVar ? $varStr : null))));
            $readingSecs = $seconds . $spacing . ucwords(strtolower($secStr . "'" . ((true === $secVar ? $varStr : null))));
        }

        return [
            'time' => [
                'formatted' => [
                    'minutes' => html_entity_decode($readingMins),
                    'seconds' => html_entity_decode($readingSecs),
                ],
                'raw' => [
                    'minutes' => $minutes,
                    'seconds' => $seconds,
                ],
            ],
            'words' => [
                'total'   => $wordCount,
                'chars'   => strlen(strip_tags($story)),
                'article' => $story,
            ]
        ];
    }

    public function formatReadCount(int $readCounts, $str = 'Read', $multiple = 's', $spacing = null): array
    {

        $spacing     = (!empty($spacing) ? $spacing : "&nbsp;");
        $data        = [];
        $data['raw'] = $readCounts;

        if ($readCounts >=1) {
            if ($readCounts > 1) {
                $data['formatted'] = $readCounts . $spacing . ucfirst($str . $multiple);
            } elseif ($readCounts === 1) {
                $data['formatted'] = $readCounts . $spacing . ucfirst($str);
            } else {
                $data['formatted'] = $readCounts . $spacing . ucfirst($str);
            }
        }else{
            $data['formatted'] = $readCounts . $spacing . ucfirst($str);
        }

        return $data;
    }

    /**
     * Truncate String with or without ellipsis
     * @param  string  $string String to truncate
     * @param  int  $maxLength Maximum length of string
     * @param  boolean $addEllipsis if True, "..." is added in the end of the string, default true
     * @param  boolean $wordsafe if True, Words will not be cut in the middle
     * @return string Shotened Text
     */
    public function shortenString($string, $maxLength, $addEllipsis = true, $wordsafe = false) {
        $ellipsis  = '';
        $maxLength = max($maxLength, 0);
        if (mb_strlen($string) <= $maxLength):
            return $string;
        endif;

        if ($addEllipsis):
            $ellipsis = mb_substr('...', 0, $maxLength);
            $maxLength-= mb_strlen($ellipsis);
            $maxLength = max($maxLength, 0);
        endif;

        if ($wordsafe):
            $string = preg_replace('/\s+?(\S+)?$/', '', mb_substr($string, 0, $maxLength));
        else:
            $string = mb_substr($string, 0, $maxLength);
        endif;

        if ($addEllipsis):
            $string.= $ellipsis;
        endif;

        return $string;
    }

    /**
     * Converts all accent characters to ASCII characters.
     * If there are no accent characters, then the string given is just returned.
     *
     * @param string $string Text that might have accent characters
     * @return string Filtered  string with replaced "nice" characters
     */
    public function removeAccents(string $string): string
    {
        return (S::create($string))->toTransliterate();
    }


    public function buildSearchTerms(string $searchTerm, int $strLength = 10): array
    {

        // return false if we don't have a usable word
        if (empty($searchTerm)) {return [];}

        // split given word into multiple terms
        $terms = [];
        foreach (explode(' ', $searchTerm) as $term)
        {
            $term = trim($term);
            if (!empty($term))
            {
                $terms[] = $term;
            }
        }

        // merge split terms with the original word
        $terms = array_merge($terms, [$searchTerm]);

        // callback function to build a search criteria of terms
        $build = function ($term, $strLength)
        {
            $firstChar = LStr::substr($term, 0, 1);
            $lastChar  = LStr::substr($term, -1);

            // any value based on length

            // generate character length pattern
            $alphaPattern = function ($n)
            {
                $pattern = [];
                $char    = '';

                // initializing value
                // corresponding to 'A'
                // ASCII value
                $num = 65;

                // outer loop to handle
                // number of rows
                // n in this case
                for ($i = 0; $i < $n; $i++)
                {

                    // inner loop to handle
                    // number of columns
                    // values changing acc.
                    // to outer loop
                    for ($j = 0; $j <= $i; $j++ )
                    {
                        // printing char value
                        $char .= "_";
                    }

                    // incrementing number
                    $num         = $num + 1;

                    // store generated pattern
                    $pattern[$i] = $char;
                }

                return $pattern;
            };
            $patterns     = [];

            if ($strLength >= 1)
            {
                foreach ($alphaPattern($strLength) as $pattern)
                {
                    $patterns[] = trim($pattern)."$term%";    // WHERE column LIKE '_$word%'	Finds any values that have "$word" in the second position

                    $patterns[] = "$term".trim($pattern)."%"; // WHERE column LIKE '$word_%'	Finds any values that start with "$word" and are at least X characters in length

                }
            }

            return array_merge([
                "$firstChar%", // WHERE column LIKE '$firstChar%'	Finds any values that start with "$firstChar"
                "%$lastChar",  // WHERE column LIKE '%$lastChar'	Finds any values that end with "$lastChar"
                "%$term%",     // WHERE column LIKE '%$word%'	Finds any values that have "$word" in any position

                // WHERE column LIKE '$firstChar%$lastChar'	Finds any values that start with "$firstChar" and ends with "$lastChar"
                "%$firstChar%",
                "%$lastChar%",
            ], $patterns);
        };

        // build list of terms
        $output = [];
        foreach ($terms as $searchTerm)
        {
            $output[] = $build($searchTerm, $strLength);
        }

        // flatten given array of arrays
        return array_merge(...array_values($output));
    }

    public function slugify($string, string|array|null $options = '-', $config = [], RuleProviderInterface $provider = null): ?string
    {
        return (new Slugify($config, $provider))->slugify($string, $options);
    }

    public function removeQueryStringVars(?string $url, array|string $key): ?string
    {
        if (! is_array($key)) {
            $key = [$key];
        }

        foreach ($key as $item) {
            $url = preg_replace('/(.*)(?|&)' . $item . '=[^&]+?(&)(.*)/i', '$1$2$4', $url . '&');
            $url = substr($url, 0, -1);
        }

        return $url;
    }

    public function compare(): Compare
    {
        return new Compare();
    }

    /**
     * Generates title text from a dirty text
     *
     * @param string $text
     * @param string $with
     * @return string
     */
    public function makeTitle(string $text, string $with = ' '): string
    {
        $text = trim($text);
        $text = str_replace([
            '/',
            '\\',
            ':',
            ';',
            '!',
            '@',
            '#',
            '$',
            '%',
            '^',
            '*',
            '(',
            ')',
            '_',
            '-',
            '+',
            '=',
            '|',
            '{',
            '}',
            '[',
            ']',
            '"',
            "'",
            '<',
            '>',
            ',',
            '?',
            '~',
            '`',
            '&',
            ' ',
            '.',
        ], $with, $text);

        // Finally run preg_replace function. This will replace anything that isn't a letter, number or space.
        return mb_convert_case(preg_replace("/[^a-zA-Z 0-9]+/", $with, $text), MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * This method masks parts of a given string
     *
     * @param string|int $string         the number address to mask
     * @param int        $maskPercentile the percent of the number to mask
     * @param string     $maskChar       the character to use to mask with
     *
     * @return string $result
     */
    public function maskString(string|int $string, int $maskPercentile = 60, string $maskChar = '*'): string
    {

        $maskPercentile  = $maskPercentile >= 100 ? 99 : $maskPercentile;
        $numberLength    = mb_strlen((string) $string);
        $numberMaskCount = floor($numberLength * $maskPercentile / 100);
        $numberOffset    = floor(( $numberLength - $numberMaskCount ) / 2);

        return substr($string, 0, (int) $numberOffset)
            .str_repeat( $maskChar, (int) $numberMaskCount)
            .substr( $string, (int) $numberMaskCount);
    }

}
