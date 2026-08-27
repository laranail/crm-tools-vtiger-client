<?php declare(strict_types=1);

namespace Simtabi\Pheg\Toolbox;

/**
 * Class PhpDocs
 * @package Simtabi\Pheg\Toolbox
 */
final class PhpDocs
{

    public function __construct() {}

    /**
     * Simple parse of PHPDocs.
     * Example or return value
     *  [
     *      'description' => 'Simple parse of PHPDocs. Example or return value',
     *      'params'      => [
     *          'param'  => ['string $phpDoc'],
     *          'return' => ['array']
     *      ]
     *  ]
     *
     * @param string $phpDoc
     * @return array
     */
    public function parse(string $phpDoc): array
    {
        $result = [
            'description' => '',
            'params'      => [],
        ];

        // split at each line
        $lines = (array)preg_split("/(\r?\n)/", $phpDoc);
        foreach ($lines as $line) {
            // if starts with an asterisk
            if (preg_match('/^(?=\s+?\*[^\/])(.+)/', (string)$line, $matches)) {
                // remove wrapping whitespace
                $info = trim($matches[1]);

                // remove leading asterisk
                $info = (string)preg_replace('/^(\*\s+?)/', '', $info);

                // if it doesn't start with an "@" symbol
                // then add to the description

                $firstChar = $info[0] ?? null;
                if ($firstChar !== "@") {
                    $result['description'] .= "\n$info";
                    continue;
                }

                // get the name of the param
                preg_match('/@(\w+)/', $info, $matches);
                $paramName = $matches[1];

                // remove the param from the string
                $value     = str_replace("@{$paramName} ", '', $info);

                // if the param hasn't been added yet, create a key for it
                if (!isset($result['params'][$paramName])) {
                    $result['params'][$paramName] = [];
                }

                // push the param value into place
                $result['params'][$paramName][] = trim($value);
            }
        }

        $result['description'] = trim($result['description']);

        return $result;
    }
}
