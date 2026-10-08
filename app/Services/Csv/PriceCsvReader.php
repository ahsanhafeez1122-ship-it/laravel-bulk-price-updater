<?php

namespace App\Services\Csv;

use App\Exceptions\CsvFormatException;
use Generator;
use SplFileObject;

/**
 * Reads price spreadsheets exported from Excel, Google Sheets, Shopify or Magento.
 *
 * Copes with what real files contain: a UTF-8 BOM, comma / semicolon / tab delimiters,
 * different header names for the same column, blank lines and stray spaces.
 */
class PriceCsvReader
{
    /** Accepted header names per column, compared lowercase with spaces/underscores removed. */
    private const HEADERS = [
        'sku' => ['sku', 'productsku', 'variantsku', 'itemcode', 'productcode'],
        'price' => ['price', 'newprice', 'saleprice', 'variantprice', 'sellingprice'],
        'compare_at' => ['compareatprice', 'compareat', 'variantcompareatprice', 'rrp', 'wasprice', 'specialfromprice'],
    ];

    public function __construct(private readonly int $maxRows = 10000)
    {
    }

    /**
     * @return Generator<int, array{sku: string, price: string, compare_at: ?string}> keyed by line number
     *
     * @throws CsvFormatException
     */
    public function read(string $path): Generator
    {
        $file = new SplFileObject($path, 'r');
        $firstLine = (string) $file->fgets();
        $firstLine = preg_replace('/^\xEF\xBB\xBF/', '', $firstLine) ?? $firstLine;

        if (trim($firstLine) === '') {
            throw new CsvFormatException('The file is empty.');
        }

        $delimiter = $this->detectDelimiter($firstLine);
        $columns = $this->mapHeader(str_getcsv(trim($firstLine), $delimiter, '"', ''));

        // No SKIP_EMPTY flag: blank lines must still be counted, so error messages point at the right line.
        $file->setFlags(0);
        $file->setCsvControl($delimiter, '"', '');

        $line = 1;
        $rows = 0;
        while (! $file->eof()) {
            $cells = $file->fgetcsv();
            $line++;
            if (! is_array($cells) || $cells === [null] || implode('', array_map('trim', array_map('strval', $cells))) === '') {
                continue;
            }

            if (++$rows > $this->maxRows) {
                throw new CsvFormatException(sprintf('The file has more than %s rows. Split it into smaller files.', number_format($this->maxRows)));
            }

            yield $line => [
                'sku' => trim((string) ($cells[$columns['sku']] ?? '')),
                'price' => trim((string) ($cells[$columns['price']] ?? '')),
                'compare_at' => isset($columns['compare_at']) ? (trim((string) ($cells[$columns['compare_at']] ?? '')) ?: null) : null,
            ];
        }

        if ($rows === 0) {
            throw new CsvFormatException('The file has a header row but no prices.');
        }
    }

    private function detectDelimiter(string $headerLine): string
    {
        $counts = [',' => substr_count($headerLine, ','), ';' => substr_count($headerLine, ';'), "\t" => substr_count($headerLine, "\t")];
        arsort($counts);

        return (string) array_key_first($counts);
    }

    /**
     * @param  array<int, string|null>  $header
     * @return array<string, int> column name => index
     */
    private function mapHeader(array $header): array
    {
        $columns = [];
        foreach ($header as $index => $name) {
            $key = preg_replace('/[\s_\-]+/', '', strtolower(trim((string) $name)));
            foreach (self::HEADERS as $column => $aliases) {
                if (! isset($columns[$column]) && in_array($key, $aliases, true)) {
                    $columns[$column] = $index;
                }
            }
        }

        $missing = array_diff(['sku', 'price'], array_keys($columns));
        if ($missing !== []) {
            throw new CsvFormatException(sprintf(
                'Missing column(s): %s. The first row must have headers, e.g. "sku,price,compare_at_price".',
                implode(', ', $missing)
            ));
        }

        return $columns;
    }
}
