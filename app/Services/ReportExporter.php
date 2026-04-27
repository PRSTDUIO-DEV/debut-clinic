<?php

namespace App\Services;

use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExporter
{
    /**
     * Stream a CSV file with UTF-8 BOM (so Excel reads Thai correctly).
     *
     * @param array<int, array<string, mixed>> $rows
     * @param array<int, string> $headers
     */
    public function csv(array $rows, array $headers, string $filename): StreamedResponse
    {
        $callback = function () use ($rows, $headers) {
            $handle = fopen('php://output', 'w');
            // BOM for Excel UTF-8
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $headers);
            foreach ($rows as $row) {
                fputcsv($handle, array_values($this->flattenRow($row, $headers)));
            }
            fclose($handle);
        };

        $headersHttp = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, no-cache',
        ];

        return response()->stream($callback, 200, $headersHttp);
    }

    /**
     * Map row to header columns; nested keys with dots ("patient.name") supported.
     */
    private function flattenRow(array $row, array $headers): array
    {
        $out = [];
        foreach ($headers as $key) {
            if (str_contains($key, '.')) {
                $value = $row;
                foreach (explode('.', $key) as $segment) {
                    if (! is_array($value) || ! array_key_exists($segment, $value)) {
                        $value = null;
                        break;
                    }
                    $value = $value[$segment];
                }
                $out[] = is_scalar($value) ? $value : ($value === null ? '' : json_encode($value, JSON_UNESCAPED_UNICODE));
            } else {
                $value = $row[$key] ?? '';
                $out[] = is_scalar($value) ? $value : ($value === null ? '' : json_encode($value, JSON_UNESCAPED_UNICODE));
            }
        }

        return $out;
    }
}
