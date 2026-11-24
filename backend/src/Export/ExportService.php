<?php

declare(strict_types=1);

namespace App\Export;

use App\Models\DatasetColumn;
use App\Models\DatasetRecord;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

final class ExportService
{
    public function __construct(private readonly string $exportDirectory)
    {
        if (!is_dir($this->exportDirectory)) {
            mkdir($this->exportDirectory, 0775, true);
        }
    }

    /** @param DatasetColumn[] $columns @param DatasetRecord[] $records */
    public function exportCsv(array $columns, array $records, string $datasetName, string $delimiter = ','): string
    {
        $delimiter = match ($delimiter) {
            'semicolon' => ';', 'tab' => "\t", 'comma' => ',', default => ',',
        };
        $filename = $this->safeFilename($datasetName, 'csv');
        $path = $this->exportDirectory . '/' . $filename;
        $handle = fopen($path, 'w');
        if ($handle === false) throw new \RuntimeException('Could not create CSV export file.');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, array_map(static fn (DatasetColumn $column): string => $column->label, $columns), $delimiter, '"', "\\");
        foreach ($records as $record) {
            $row = [];
            foreach ($columns as $column) {
                $row[] = $this->csvValue($record->data[$column->name] ?? null);
            }
            fputcsv($handle, $row, $delimiter, '"', "\\");
        }
        fclose($handle);
        return $path;
    }

    /** @param DatasetColumn[] $columns @param DatasetRecord[] $records */
    public function exportXlsx(array $columns, array $records, string $datasetName): string
    {
        $filename = $this->safeFilename($datasetName, 'xlsx');
        $path = $this->exportDirectory . '/' . $filename;
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Dataset');

        foreach ($columns as $index => $column) {
            $cell = $sheet->getCell([$index + 1, 1]);
            $cell->setValueExplicit($column->label, DataType::TYPE_STRING);
        }
        $sheet->getStyle('1:1')->getFont()->setBold(true);
        $sheet->getStyle('1:1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9E2F3');

        foreach ($records as $rowIndex => $record) {
            foreach ($columns as $columnIndex => $column) {
                $value = $record->data[$column->name] ?? null;
                $coordinate = $sheet->getCell([$columnIndex + 1, $rowIndex + 2]);
                $this->setSpreadsheetValue($coordinate, $column, $value);
            }
        }

        foreach ($columns as $index => $column) {
            $sheet->getColumnDimensionByColumn($index + 1)->setAutoSize(true);
        }
        $sheet->setAutoFilter($sheet->calculateWorksheetDimension());
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();
        return $path;
    }

    private function csvValue(mixed $value): string
    {
        if ($value === null) return '';
        if (is_bool($value)) return $value ? 'true' : 'false';
        return (string) $value;
    }

    private function setSpreadsheetValue($coordinate, DatasetColumn $column, mixed $value): void
    {
        if ($value === null) {
            $coordinate->setValueExplicit('', DataType::TYPE_STRING);
            return;
        }
        if ($column->dataType === 'integer') {
            $coordinate->setValueExplicit((int) $value, DataType::TYPE_NUMERIC);
        } elseif ($column->dataType === 'decimal') {
            $coordinate->setValueExplicit((float) $value, DataType::TYPE_NUMERIC);
            $coordinate->getStyle()->getNumberFormat()->setFormatCode('#,##0.00');
        } elseif ($column->dataType === 'boolean') {
            $coordinate->setValueExplicit((bool) $value ? 'true' : 'false', DataType::TYPE_STRING);
        } elseif ($column->dataType === 'date') {
            $timestamp = strtotime((string) $value);
            if ($timestamp !== false) {
                $coordinate->setValueExplicit((float) \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel((new \DateTimeImmutable('@' . $timestamp))), DataType::TYPE_NUMERIC);
                $coordinate->getStyle()->getNumberFormat()->setFormatCode('yyyy-mm-dd');
            } else {
                $coordinate->setValueExplicit((string) $value, DataType::TYPE_STRING);
            }
        } elseif ($column->dataType === 'datetime') {
            $timestamp = strtotime((string) $value);
            if ($timestamp !== false) {
                $coordinate->setValueExplicit((float) \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel((new \DateTimeImmutable('@' . $timestamp))), DataType::TYPE_NUMERIC);
                $coordinate->getStyle()->getNumberFormat()->setFormatCode('yyyy-mm-dd hh:mm:ss');
            } else {
                $coordinate->setValueExplicit((string) $value, DataType::TYPE_STRING);
            }
        } else {
            $coordinate->setValueExplicit((string) $value, DataType::TYPE_STRING);
        }
    }

    private function safeFilename(string $datasetName, string $extension): string
    {
        $normalized = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $datasetName);
        $normalized = is_string($normalized) ? $normalized : 'dataset';
        $slug = strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $normalized), '-'));
        if ($slug === '') $slug = 'dataset';
        return $slug . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $extension;
    }
}
