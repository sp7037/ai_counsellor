<?php

namespace App\Services\Knowledge;

use App\Enums\Knowledge\KnowledgeImportType;
use Illuminate\Validation\ValidationException;

class KnowledgeImportCsvParser
{
    /**
     * @return array{headers: array<int, string>, rows: array<int, array<string, string>>}
     */
    public function parse(string $path, KnowledgeImportType $type): array
    {
        if (! is_readable($path)) {
            throw ValidationException::withMessages(['file' => 'Uploaded file could not be read.']);
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw ValidationException::withMessages(['file' => 'Uploaded file could not be opened.']);
        }

        $headerRow = fgetcsv($handle);

        if ($headerRow === false || $headerRow === [null]) {
            fclose($handle);

            throw ValidationException::withMessages(['file' => 'CSV file is empty.']);
        }

        $headers = $this->normalizeHeaders($headerRow);
        $this->assertRequiredHeaders($headers, $type);

        $rows = [];
        $lineNumber = 1;

        while (($data = fgetcsv($handle)) !== false) {
            $lineNumber++;

            if ($this->isBlankRow($data)) {
                continue;
            }

            if (count($data) < count($headers)) {
                $data = array_pad($data, count($headers), '');
            }

            $row = [];

            foreach ($headers as $index => $header) {
                $row[$header] = trim((string) ($data[$index] ?? ''));
            }

            $rows[] = $row;

            if (count($rows) > config('knowledge.max_import_rows', 500)) {
                fclose($handle);

                throw ValidationException::withMessages([
                    'file' => 'CSV exceeds the maximum of '.config('knowledge.max_import_rows', 500).' rows.',
                ]);
            }
        }

        fclose($handle);

        if ($rows === []) {
            throw ValidationException::withMessages(['file' => 'CSV file has headers but no data rows.']);
        }

        return [
            'headers' => $headers,
            'rows' => $rows,
        ];
    }

    /**
     * @param  array<int, string|null>  $headers
     * @return array<int, string>
     */
    private function normalizeHeaders(array $headers): array
    {
        return array_values(array_filter(array_map(function (?string $header): string {
            $header = strtolower(trim((string) $header));
            $header = preg_replace('/^\xEF\xBB\xBF/', '', $header) ?? $header;

            return preg_replace('/\s+/', '_', $header) ?? $header;
        }, $headers)));
    }

    /**
     * @param  array<int, string>  $headers
     */
    private function assertRequiredHeaders(array $headers, KnowledgeImportType $type): void
    {
        $missing = array_diff($type->requiredHeaders(), $headers);

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'file' => 'Missing required CSV columns: '.implode(', ', $missing).'.',
            ]);
        }
    }

    /**
     * @param  array<int, string|null>  $row
     */
    private function isBlankRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }
}
