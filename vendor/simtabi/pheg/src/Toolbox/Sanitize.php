<?php declare(strict_types=1);

namespace Simtabi\Pheg\Toolbox;

use Simtabi\Pheg\Toolbox\Arr\Arr;

final class Sanitize
{

    private Arr $arr;

    public function __construct()
    {
        $this->arr = new Arr;
    }

    /**
     * Filter characters in a string.
     *
     * @param string $input
     *   The input string.
     * @param array $filter
     *   An array of string replacements to use on the identifier.
     *
     * @return string
     *   The filtered string.
     */
    public function filterString(string $input, array $filter = [
        ' ' => '-',
        '  ' => '-',
        '_' => '-',
        '/' => '-',
        '[' => '-',
        ']' => '',
    ]): string
    {
        // Replace filter values in input string.
        $input = str_replace(array_keys($filter), array_values($filter), $input);
        // Strip invalid characters.
        // Valid characters are:
        // - the hyphen (U+002D)
        // - a-z (U+0030 - U+0039)
        // - A-Z (U+0041 - U+005A)
        // - the underscore (U+005F)
        // - 0-9 (U+0061 - U+007A)
        // - ISO 10646 characters U+00A1 and higher
        // We strip out any character not in the above list.
        return preg_replace('/[^\x{002D}\x{0030}-\x{0039}\x{0041}-\x{005A}\x{005F}\x{0061}-\x{007A}\x{00A1}-\x{FFFF}]/u', '', $input);
    }

    /**
     * Returns the input converted by the htmlentities function.
     *
     * @param mixed $input
     * @param string $charset
     * @return mixed
     */
    public function htmlEntities($input, string $charset = 'UTF-8')
    {
        return $this->arr->recurse(
            $input,
            function($input) use ($charset) {
                return htmlentities($input, ENT_QUOTES, $charset);
            }
        );
    }

    /**
     * Returns the input converted by the html_entity_decode function.
     *
     * @param mixed $input
     * @param string $charset
     * @return mixed
     */
    public function htmlEntityDecode($input, string $charset = 'UTF-8')
    {
        return $this->arr->recurse(
            $input,
            function($input) use ($charset) {
                return html_entity_decode($input, ENT_QUOTES, $charset);
            }
        );
    }

    /**
     * Returns the input converted by the htmlspecialchars function.
     *
     * @param mixed $input
     * @param string $charset
     * @return mixed
     */
    public function htmlSpecialChars($input, string $charset = 'UTF-8')
    {
        return $this->arr->recurse(
            $input,
            function($input) use ($charset) {
                return htmlspecialchars($input, ENT_QUOTES, $charset);
            }
        );
    }
    
    public function escapeHTML($html, $decode = true){
        if($decode){
            return html_entity_decode($html);
        }else{
            return htmlentities($html, ENT_QUOTES, 'UTF-8');
        }
    }
    
    public function arrayOrObject (array|object $array) {
        if (!is_array($array) || !count($array)) { return array(); }
        foreach ($array as $k => $v) {
            if (!is_array($v) && !is_object($v)) {
                $array[$k] = htmlspecialchars(strip_tags(trim($v)));
            }
            if (is_array($v)) {
                $array[$k] = $this->arrayOrObject($v);
            }
        }
        return $array;
    }

}