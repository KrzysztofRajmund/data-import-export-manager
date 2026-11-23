<?php

declare(strict_types=1);

namespace App\Import;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use RuntimeException;

final class ImportParser
{
    public const MAX_PREVIEW_ROWS = 50;

    /** @return array{headers: array<int,string>, rows: array<int,array<int,mixed>>, worksheets: array<int,string>, worksheet: string} */
    public function parse(string $path, string $extension, int $headerRow = 1, string $delimiter = 'auto', ?string $worksheet = null): array
    {
        $extension = strtolower($extension);
        if ($extension === 'csv') return $this->parseCsv($path, $headerRow, $delimiter);
        if ($extension === 'xlsx') return $this->parseXlsx($path, $headerRow, $worksheet);
        throw new RuntimeException('Only XLSX and CSV files are supported.');
    }

    /** @return array{headers: array<int,string>, rows: array<int,array<int,mixed>>, worksheets: array<int,string>, worksheet: string} */
    private function parseCsv(string $path, int $headerRow, string $delimiter): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) throw new RuntimeException('Could not open the uploaded CSV file.');

        $sample = fread($handle, 4096);
        rewind($handle);
        $detected = $delimiter === 'auto' ? $this->detectDelimiter(is_string($sample) ? $sample : '') : $this->delimiterFromName($delimiter);

        $rows = [];
        while (($row = fgetcsv($handle, 0, $detected, '"', '\\')) !== false) $rows[] = $row;
        fclose($handle);

        if ($rows === []) throw new RuntimeException('The CSV file is empty.');
        $headerIndex = max(0, $headerRow - 1);
        if (!isset($rows[$headerIndex])) throw new RuntimeException('The configured header row does not exist.');

        $headers = $this->normalizeHeaders($rows[$headerIndex]);
        $dataRows = [];
        foreach (array_slice($rows, $headerIndex + 1) as $row) {
            if ($this->isEmptyRow($row)) continue;
            $dataRows[] = array_pad(array_slice($row, 0, count($headers)), count($headers), null);
            if (count($dataRows) >= self::MAX_PREVIEW_ROWS) break;
        }

        return ['headers' => $headers, 'rows' => $dataRows, 'worksheets' => [], 'worksheet' => ''];
    }

    /** @return array{headers: array<int,string>, rows: array<int,array<int,mixed>>, worksheets: array<int,string>, worksheet: string} */
    private function parseXlsx(string $path, int $headerRow, ?string $worksheet): array
    {
        $spreadsheet = IOFactory::load($path);
        $worksheets = [];
        foreach ($spreadsheet->getAllSheets() as $sheetItem) $worksheets[] = $sheetItem->getTitle();
        if ($worksheets === []) throw new RuntimeException('The XLSX file contains no worksheets.');

        $sheetName = $worksheet !== null && in_array($worksheet, $worksheets, true) ? $worksheet : $worksheets[0];
        $sheet = $spreadsheet->getSheetByName($sheetName);
        if ($sheet === null) throw new RuntimeException('The selected worksheet could not be found.');

        $highestColumn = $sheet->getHighestDataColumn();
        $highestRow = $sheet->getHighestDataRow();
        $headerIndex = max(1, $headerRow);
        $columnCount = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);
        $headers = [];
        for ($column = 1; $column <= $columnCount; $column++) {
            $headers[] = $this->normalizeHeader($sheet->getCell([$column, $headerIndex])->getValue(), $column);
        }

        $dataRows = [];
        $limit = min($highestRow, $headerIndex + self::MAX_PREVIEW_ROWS);
        for ($rowNumber = $headerIndex + 1; $rowNumber <= $limit; $rowNumber++) {
            $row = [];
            $hasValue = false;
            for ($column = 1; $column <= $columnCount; $column++) {
                $cell = $sheet->getCell([$column, $rowNumber]);
                $value = $cell->getValue();
                if ($value !== null && $value !== '') $hasValue = true;
                if ($value instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) $value = $value->getPlainText();
                if (is_numeric($value) && Date::isDateTime($cell, $value, true)) {
                    $value = Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
                } elseif (is_string($value) && $value === '') {
                    $value = null;
                }
                $row[] = $value;
            }
            if ($hasValue) $dataRows[] = $row;
        }

        $spreadsheet->disconnectWorksheets();
        return ['headers' => $headers, 'rows' => $dataRows, 'worksheets' => $worksheets, 'worksheet' => $sheetName];
    }

    private function normalizeHeaders(array $headers): array
    {
        $normalized = [];
        foreach (array_values($headers) as $index => $header) $normalized[] = $this->normalizeHeader($header, $index + 1);
        return $normalized;
    }

    private function normalizeHeader(mixed $header, int $index): string
    {
        $value = trim((string) $header);
        return $value !== '' ? $value : 'Column ' . $index;
    }

    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) if ($value !== null && trim((string) $value) !== '') return false;
        return true;
    }

    private function detectDelimiter(string $sample): string
    {
        $counts = [',' => substr_count($sample, ','), ';' => substr_count($sample, ';'), "\t" => substr_count($sample, "\t")];
        arsort($counts);
        return (string) array_key_first($counts);
    }

    private function delimiterFromName(string $delimiter): string
    {
        return match ($delimiter) {
            'semicolon' => ';', 'tab' => "\t", 'comma' => ',', default => ','
        };
    }
}
