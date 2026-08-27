<?php declare(strict_types=1);

namespace Simtabi\Pheg\Toolbox;

final class CsvParser
{
    public const LENGTH_LIMIT = 10000000;

    public function __construct() {}

    /**
     * Simple parser for CSV files
     *
     * @param string $csvFile
     * @param string $delimiter
     * @param string $enclosure
     * @param bool   $hasHeader
     * @return array
     */
    public function parse(string $csvFile, string $delimiter = ';', string $enclosure = '"', bool $hasHeader = true): array {
        $result     = [];
        $headerKeys = [];
        $rowCounter = 0;

        if (($handle = fopen($csvFile, 'rb')) !== false) {
            while (($row = fgetcsv($handle, self::LENGTH_LIMIT, $delimiter, $enclosure)) !== false) {
                $row = (array)$row;
                if ($rowCounter === 0 && $hasHeader) {
                    $headerKeys = $row;
                } elseif ($hasHeader) {
                    $assocRow = [];

                    foreach ($headerKeys as $colIndex => $colName) {
                        $colName = (string)$colName;
                        $assocRow[$colName] = $row[$colIndex];
                    }

                    $result[] = $assocRow;
                } else {
                    $result[] = $row;
                }

                $rowCounter++;
            }

            fclose($handle);
        }

        return $result;
    }
}
