<?php

declare(strict_types=1);

namespace App\Import;

use App\Repositories\DatasetColumnRepository;
use App\Repositories\DatasetRecordRepository;
use App\Repositories\DatasetRepository;
use App\Services\DatasetColumnService;
use App\Services\DatasetRecordService;
use App\Services\DatasetService;
use mysqli;
use RuntimeException;

final class ImportService
{
    private const MAX_UPLOAD_BYTES = 10 * 1024 * 1024;

    public function __construct(private readonly mysqli $connection)
    {
    }

    public function storeUpload(array $file): array
    {
        if (!isset($file['error']) || (int) $file['error'] !== UPLOAD_ERR_OK) throw new RuntimeException($this->uploadError((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE)));
        $size = (int) ($file['size'] ?? 0);
        if ($size < 1 || $size > self::MAX_UPLOAD_BYTES) throw new RuntimeException('Upload size must be between 1 byte and 10 MB.');

        $original = basename((string) ($file['name'] ?? 'upload'));
        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if (!in_array($extension, ['xlsx', 'csv'], true)) throw new RuntimeException('Only .xlsx and .csv files are allowed.');

        $tmpName = (string) ($file['tmp_name'] ?? '');
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = is_resource($finfo) ? finfo_file($finfo, $tmpName) : null;
        if (is_resource($finfo)) finfo_close($finfo);

        if ($extension === 'xlsx') {
            $handle = fopen($tmpName, 'rb');
            $signature = $handle === false ? '' : fread($handle, 4);
            if ($handle !== false) fclose($handle);

            if ($signature !== "PK\x03\x04") {
                throw new RuntimeException('The uploaded file is not a valid XLSX file.');
            }

            try {
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmpName);
                $spreadsheet->disconnectWorksheets();
            } catch (\Throwable $exception) {
                throw new RuntimeException('The uploaded XLSX file could not be opened.');
            }
        } else {
            $allowedMime = ['text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel', 'application/octet-stream', 'application/x-empty'];
            if (!is_string($mime) || !in_array($mime, $allowedMime, true)) {
                throw new RuntimeException('The uploaded file type is not allowed.');
            }
        }

        $directory = dirname(__DIR__, 2) . '/storage/imports';
        if (!is_dir($directory) && !mkdir($directory, 0700, true)) throw new RuntimeException('Could not create the import storage directory.');
        $safeName = bin2hex(random_bytes(16)) . '.' . $extension;
        $destination = $directory . '/' . $safeName;
        if (!move_uploaded_file($tmpName, $destination)) throw new RuntimeException('Could not store the uploaded file securely.');
        chmod($destination, 0600);

        return ['path' => $destination, 'filename' => $original, 'extension' => $extension];
    }

    public function suggestType(array $values): string
    {
        $values = array_values(array_filter($values, static fn ($value): bool => $value !== null && (is_string($value) ? trim($value) !== '' : true)));
        if ($values === []) return 'string';

        $allBoolean = true;
        foreach ($values as $value) if (!in_array(strtolower(trim((string) $value)), ['true', 'false', 'yes', 'no', '1', '0'], true)) $allBoolean = false;
        if ($allBoolean) return 'boolean';

        $allInteger = true;
        foreach ($values as $value) if (filter_var($value, FILTER_VALIDATE_INT) === false) $allInteger = false;
        if ($allInteger) return 'integer';

        $allDecimal = true;
        foreach ($values as $value) if (!is_numeric($value)) $allDecimal = false;
        if ($allDecimal) return 'decimal';

        $allDate = true;
        foreach ($values as $value) {
            $date = \DateTimeImmutable::createFromFormat('Y-m-d', (string) $value);
            $errors = \DateTimeImmutable::getLastErrors();
            if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) $allDate = false;
        }
        if ($allDate) return 'date';
        return 'string';
    }

    public function columnName(string $label, int $index): string
    {
        $name = strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '_', $label), '_'));
        if ($name === '' || preg_match('/^[0-9]/', $name)) $name = 'column_' . ($index + 1);
        return mb_substr($name, 0, 255);
    }

    private function uploadError(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the allowed size of 10 MB.',
            UPLOAD_ERR_PARTIAL => 'The upload was incomplete.',
            UPLOAD_ERR_NO_FILE => 'Please select a file to upload.',
            default => 'The file could not be uploaded.',
        };
    }
}
