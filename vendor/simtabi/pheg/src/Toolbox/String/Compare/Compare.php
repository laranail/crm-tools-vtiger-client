<?php

namespace Simtabi\Pheg\Toolbox\String\Compare;

class Compare
{

    public function __construct()
    {
    }

    public function all($first, $second)
    {
        $start       = microtime(true);

        $similar     = $this->similarText($first, $second);
        $smg         = $this->smg($first, $second);
        $jaroWinkler = $this->jaroWinkler($first, $second);
        $levenshtein = $this->levenshtein($first, $second);

        $end = microtime(true) - $start;

        return [
            'data' => [
                'first_string'        => $first,
                'second_string'       => $second,
                'run_time_in_seconds' => $end,
            ],
            'similar_text' => $similar,
            'smg'          => $smg,
            'jaroWinkler'  => $jaroWinkler,
            'levenshtein'  => $levenshtein
        ];
    }

    /**
     * Run a basic levenshtein comparison using PHP's built-in function
     *
     * @param string $first First string to compare
     * @param string $second Second string to compare
     *
     * @return string Returns the phrase passed in
     */
    public function levenshtein(string $first, string $second): string
    {
        return (new Levenshtein())->compare($first, $second);
    }

    public function jaroWinkler(string $first, string $second): float
    {
        return (new JaroWinkler())->compare($first, $second);
    }

    public function smg(string $first, string $second): float|int
    {
        return (new SmithWatermanGotoh())->compare($first, $second);
    }

    public function similarText($first, $second): float
    {
        similar_text($first, $second, $percent);

        return $percent;
    }

    public function string(string $var1, string $var2): bool
    {
        if (strcasecmp($var1, $var2) == 0) {
            return true;
        }

        return false;
    }
}
