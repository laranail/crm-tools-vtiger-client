<?php declare(strict_types=1);

namespace Simtabi\Pheg\Toolbox\JSON;

use Exception;

class Json2File {

    protected ?string $file = null;

    public function __construct(string $file)
    {
        $this->file = $file;
    }

    // write array data to file by appending to previous contents
    public function write(array $contents, $allowDuplicate = true): bool|int
    {
        $result = false;

        if (! $allowDuplicate) {
            $isDuplicate = $this->search($contents);

            if (! $isDuplicate) {
                $result = file_put_contents($this->file, json_encode($contents) . PHP_EOL, FILE_APPEND | LOCK_EX);
            }
        } else {
            $result = file_put_contents($this->file, json_encode($contents) . PHP_EOL, FILE_APPEND | LOCK_EX);
        }

        return $result;
    }

    // write new file, useful for writing array data once in one call
    public function writeTruncated(array $contents): bool|int
    {
        return file_put_contents($this->file, json_encode($contents) . PHP_EOL, LOCK_EX);
    }

    // returns plain contents of written file
    public function readPlain($skipEmptyLines = false): string
    {
        $contents = '';

        if ($skipEmptyLines) {
            $lines = file($this->file, FILE_SKIP_EMPTY_LINES);
        } else {
            $lines = file($this->file);
        }

        foreach ($lines as $line_num => $line) {
            $contents .= "Line #<b>{$line_num}</b> : " . htmlspecialchars($line) . "<br />\n";
        }

        return $contents;
    }

    // returns written json data as array
    public function readAsArray($skipEmptyLines = false): array
    {
        $array = array();

        if ($skipEmptyLines) {
            $lines = file($this->file, FILE_SKIP_EMPTY_LINES);
        } else {
            $lines = file($this->file);
        }

        foreach ($lines as $line) {
            $array[] = json_decode($line, true);
        }

        return $array;
    }

    // returns written data as plain human readable table format
    public function readAsPlainTable()
    {
        $this->toTable($this->readAsArray());
    }

    // returns written data as html table
    public function readAsHTMLTable(): string
    {
        return $this->array2Html($this->readAsArray());
    }

    // search in given file for specified array
    public function search(array $array): bool
    {
        $data = $this->readAsArray();

        if ($data) {
            foreach ($data as $subArray) {
                if ($array === $subArray) {
                    return true;
                }
            }
        }

        return false;
    }

    // converts array to human-readable plain table
    protected function toTable($data, $echo = true)
    {
        $keys = array_keys(end($data));
        $size = array_map('strlen', $keys);

        foreach (array_map('array_values', $data) as $e) {
            $size = array_map(
                'max',
                $size,
                array_map('strlen', $e)
            );
        }

        foreach ($size as $n) {
            $form[] = "%-{$n}s";
            $line[] = str_repeat('-', $n);
        }

        $form = '| ' . implode(' | ', $form) . " |\n";
        $line = '+-' . implode('-+-', $line) . "-+\n";
        $rows = array(vsprintf($form, $keys));

        foreach ($data as $e) {
            $rows[] = vsprintf($form, $e);
        }

        $html  = "<pre>\n";
        $html .= $line . implode($line, $rows) . $line;
        $html .= "</pre>\n";

        if ($echo) {
            echo $html;
        }else{
            return $html;
        }
    }

    // converts array to html table
    protected function array2Html($array, $table = true): string
    {
        $out = '';
        $tableHeader = '';

        foreach ($array as $value) {
            if (is_array($value)) {
                if (!isset($tableHeader)) {
                    $tableHeader =
                        '<th>' .
                        implode('</th><th>', array_keys($value)) .
                        '</th>';
                }

                $value = array_keys($value);
                $out .= '<tr>';
                $out .= $this->array2Html($value, false);
                $out .= '</tr>';
            } else {
                $out .= "<td>$value</td>";
            }
        }

        if ($table) {
            return '<table>' . $tableHeader . $out . '</table>';
        } else {
            return $out;
        }
    }

    /**
     * Creating JSON file from array.
     *
     * @param array  $array    → array to be converted to JSON file
     *
     * @return bool true     → if the file is created
     *@throws Exception → couldn't create file
     */
    public function arrayToFile(array $array, bool $prettyPrint = true): bool {

        $path = str_replace(basename($this->file), '', $this->file);

        if (!empty($path) && !is_dir($path)) {

            mkdir($path, 0755, true);
        }

        $json = $prettyPrint ? json_encode($array, JSON_PRETTY_PRINT) : json_encode($array);

        $this->jsonLastError();

        if (!$file = fopen($this->file, 'w+')) {

            throw new Exception('Could not create file in ' . $this->file, 300);
        }

        fwrite($file, $json);

        return true;
    }

    /**
     * Save to array the JSON file content.
     *
     * @return array         → JSON format
     *@throws Exception → there is no file
     */
    public function fileToArray(): array {

        if (is_file($this->file)) {

            $jsonString = file_get_contents($this->file);
            $jsonArray  = json_decode($jsonString, true);

            $this->jsonLastError();

            return $jsonArray;
        }

        throw new Exception('File not found in ' . $this->file, 300);
    }

    /**
     * Check for errors.
     *
     * @return true
     * @throws Exception → JSON (encode-decode) error
     */
    public function jsonLastError(): bool
    {

        switch (json_last_error()) {
            case JSON_ERROR_NONE:
                return true;
            case JSON_ERROR_UTF8:
                throw new Exception('Malformed UTF-8 characters', 300);
            case JSON_ERROR_DEPTH:
                throw new Exception('Maximum stack depth exceeded', 300);
            case JSON_ERROR_SYNTAX:
                throw new Exception('Syntax error, malformed JSON', 300);
            case JSON_ERROR_CTRL_CHAR:
                throw new Exception('Unexpected control char found', 300);
            case JSON_ERROR_STATE_MISMATCH:
                throw new Exception('Underflow or the modes mismatch', 300);
            default:
                throw new Exception('Unknown error', 300);
        }
    }
}
