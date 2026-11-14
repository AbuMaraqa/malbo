<?php

namespace Modules\Reports\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A report as a CSV file Excel opens in Arabic: UTF-8 with a byte order mark, comma separated, the
 * header row in the viewer's language, written row by row as the rows are read.
 */
final class CsvExport
{
    /**
     * @param  list<string>  $headers
     * @param  iterable<array<int, scalar|null>>  $rows
     */
    public static function stream(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return new StreamedResponse(function () use ($headers, $rows): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers, escape: '');

            foreach ($rows as $row) {
                fputcsv($out, array_map(self::cell(...), $row), escape: '');
            }

            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * A text that a spreadsheet would run as a formula (a product named "=HYPERLINK(...)") is kept
     * as text; numbers, negative ones too, stay numbers.
     */
    private static function cell(mixed $value): string
    {
        $value = $value === null ? '' : (string) $value;

        return preg_match('/^[=+@\t\r]/', $value) || (str_starts_with($value, '-') && ! is_numeric($value)) ? "'".$value : $value;
    }
}
