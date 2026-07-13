<?php

declare(strict_types=1);

namespace WicketWP\Support;

/**
 * Safe CSV export with formula-injection prevention (AD14).
 *
 * Any cell whose first character is one of =, +, -, @, tab, or carriage return
 * is prefixed with a single tab so spreadsheet apps (Excel, LibreOffice) treat
 * it as text rather than evaluating it as a formula.
 *
 * Originally authored in wicket-wp-importer; extracted to wicket-wp-base-plugin
 * so both the importer and the account-centre plugin consume a single shared
 * implementation (WWID-1907 phase 0). The importer retains a BC class at its
 * old namespace that delegates to this class.
 */
final class CsvExporter
{
    /**
     * Leading characters that trigger escaping. All single-byte ASCII, so a
     * byte-level check on $string[0] is correct even for multibyte values.
     */
    private const DANGEROUS_LEADING_CHARS = ['=', '+', '-', '@', "\t", "\r"];

    /**
     * Escape a single cell value against CSV formula injection.
     */
    public function escapeCellValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        $s = is_string($value) ? $value : (string) $value;

        if ($s === '') {
            return '';
        }

        if (in_array($s[0], self::DANGEROUS_LEADING_CHARS, true)) {
            return "\t" . $s;
        }

        return $s;
    }

    /**
     * Write one escaped row to a CSV handle.
     *
     * @param resource    $handle  Open write handle (e.g. php://output).
     * @param list<mixed> $values  Cell values for the row.
     */
    public function writeRow(array $values, $handle): void
    {
        $escaped = array_map(fn (mixed $v): string => $this->escapeCellValue($v), array_values($values));

        fputcsv($handle, $escaped, ',', '"', '');
    }

    /**
     * Stream a CSV download to the browser and terminate the request.
     *
     * Note: calling exit() from a REST callback short-circuits WP REST's normal
     * dispatch (rest_post_dispatch, rest_pre_serve_request, rest_send_cors_headers).
     * Accepted tradeoff for v1 admin-only downloads (same-origin). If CSV routes
     * ever need to serve a different origin or interoperate with CORS/audit
     * plugins, switch to building the body into a string and returning a
     * WP_REST_Response with these headers instead.
     *
     * @param string            $filename Download filename (sanitized via sanitize_file_name()).
     * @param list<list<mixed>> $rows     Rows to write; the first row is the header row.
     */
    public function download(string $filename, array $rows): never
    {
        nocache_headers();
        header('Content-Description: File Transfer');
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . sanitize_file_name($filename) . '"');
        // Prevent MIME sniffing from re-interpreting the CSV as HTML.
        header('X-Content-Type-Options: nosniff');

        $out = fopen('php://output', 'wb');
        if ($out !== false) {
            foreach ($rows as $row) {
                $this->writeRow($row, $out);
            }
            fclose($out);
        }

        exit;
    }
}
