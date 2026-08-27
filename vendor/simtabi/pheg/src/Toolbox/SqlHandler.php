<?php declare(strict_types=1);

namespace Simtabi\Pheg\Toolbox;

use Simtabi\Pheg\Toolbox\Transfigures\Transfigure;

final class SqlHandler
{

    public function __construct() {}

    /**
     * Splits a string of multiple queries into an array of individual queries.
     * Single line or line end comments and multi line comments are stripped off.
     *
     * @param string $sql Input SQL string with which to split into individual queries.
     * @return array
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function splitSql(string $sql): array
    {
        $start     = 0;
        $open      = false;
        $comment   = false;
        $endString = '';
        $end       = strlen($sql);
        $queries   = [];
        $query     = '';

        for ($i = 0; $i < $end; $i++) {
            $current      = $sql[$i];
            $current2     = substr($sql, $i, 2);
            $current3     = substr($sql, $i, 3);
            $lenEndString = strlen($endString);
            $testEnd      = substr($sql, $i, $lenEndString);

            $quotedWithBackslash = $current === '"' || $current === "'" || $current2 === '--' ||
                ($current2 === '/*' && $current3 !== '/*!' && $current3 !== '/*+') ||
                ($current === '#' && $current3 !== '#__') ||
                ($comment && $testEnd === $endString);

            if ($quotedWithBackslash) {
                // Check if quoted with previous backslash
                $num = 2;

                while ($sql[$i - $num + 1] === '\\' && $num < $i) {
                    $num++;
                }

                // Not quoted
                if ($num % 2 === 0) {
                    if ($open) {
                        if ($testEnd === $endString) {
                            if ($comment) {
                                $comment = false;
                                if ($lenEndString > 1) {
                                    $i += ($lenEndString - 1);
                                    $current = $sql[$i];
                                }
                                $start = $i + 1;
                            }
                            $open = false;
                            $endString = '';
                        }
                    } else {
                        $open = true;
                        if ('--' === $current2) {
                            $endString = "\n";
                            $comment = true;
                        } elseif ('/*' === $current2) {
                            $endString = '*/';
                            $comment = true;
                        } elseif ('#' === $current) {
                            $endString = "\n";
                            $comment = true;
                        } else {
                            $endString = $current;
                        }
                        if ($comment && $start < $i) {
                            $query .= substr($sql, $start, (int)($i - $start));
                        }
                    }
                }
            }

            if ($comment) {
                $start = $i + 1;
            }

            if (($current === ';' && !$open) || $i === $end - 1) {
                if ($start <= $i) {
                    $query .= substr($sql, $start, $i - $start + 1);
                }
                $query = trim($query);

                if ($query) {
                    if ($current !== ';') {
                        $query .= ';';
                    }
                    $queries[] = $query;
                }

                $query = '';
                $start = $i + 1;
            }
        }

        return $queries;
    }

    public function parseSqlMessage($message){

        // https://gist.github.com/IlanFrumer/7888809

        // pattern one lookup
        $patternOne    = "/^SQLSTATE\[\w+\]:[^:]+:\s*(\d*)\s*(.*)/";
        $matchOneCount = preg_match($patternOne, $message, $matchOne);

        // pattern two lookup
        $patternTwo    = "/SQLSTATE\[(\w+)\] \[(\w+)\] (.*)/";
        $matchTwoCount = preg_match($patternTwo, $message, $matchTwo);

        // default message variables
        $pdoMessage = null;
        $sqlState   = null;
        $code       = null;

        // if match one has something
        if ($matchOneCount) {
            $pdoMessage = $matchOne[2];
            $sqlState   = $matchOne[0];
            $code       = $matchOne[1];
        }elseif ($matchTwoCount) {
            $pdoMessage = $matchTwo[2];
            $sqlState   = $matchTwo[0];
            $code       = $matchTwo[1];
        }

        return (new Transfigure())->toObject([
            'message' => $pdoMessage,
            'state'   => $sqlState,
            'code'    => !empty($code) ? $code : 0,
        ]);
    }

    public function generateSearchTerm($term)
    {
        return preg_split('/\s+/', str_replace(['-', '+', '<', '>', '@', '(', ')', '~'], ' ', $term), -1, PREG_SPLIT_NO_EMPTY);
    }
}