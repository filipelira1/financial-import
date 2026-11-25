<?php

namespace App\Http\Responses;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a CSV file row by row, so a large export never sits whole in memory.
 *
 * - UTF-8 with a BOM, so spreadsheet programs show accents correctly.
 * - ";" for Portuguese (what a Brazilian Excel expects) and "," otherwise. The importer reads
 *   both, so an exported file can be imported again as it is.
 * - Values that a spreadsheet would run as a formula (=, +, -, @) get a leading apostrophe
 *   (CSV injection, OWASP). Only text needs it: numbers are written untouched.
 */
final class CsvDownload
{
    private const string UTF8_BOM = "\xEF\xBB\xBF";

    /**
     * @param  list<string>  $header
     * @param  iterable<list<string|int>>  $rows
     */
    public static function make(string $filename, array $header, iterable $rows, string $locale): StreamedResponse
    {
        $separator = str_starts_with($locale, 'pt') ? ';' : ',';

        return response()->streamDownload(function () use ($header, $rows, $separator): void {
            $out = fopen('php://output', 'wb');
            fwrite($out, self::UTF8_BOM);
            fputcsv($out, $header, $separator, '"', '');

            foreach ($rows as $index => $row) {
                fputcsv($out, array_map(self::safeCell(...), $row), $separator, '"', '');

                if ($index % 1000 === 999) {
                    flush();
                }
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]);
    }

    private static function safeCell(string|int $value): string|int
    {
        if (is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }
}
