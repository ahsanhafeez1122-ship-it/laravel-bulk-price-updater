<?php

namespace Tests\Unit;

use App\Exceptions\CsvFormatException;
use App\Services\Csv\PriceCsvReader;
use PHPUnit\Framework\TestCase;

class PriceCsvReaderTest extends TestCase
{
    /** @var string[] */
    private array $files = [];

    protected function tearDown(): void
    {
        array_map('unlink', $this->files);
        parent::tearDown();
    }

    public function test_reads_excel_export_with_bom_and_quoted_values(): void
    {
        $rows = $this->read("\xEF\xBB\xBFSKU,Price,Compare At Price\r\nA-1,\"1,299.00\",\r\nB-2,£5.00,£7.00\r\n");

        $this->assertSame([
            2 => ['sku' => 'A-1', 'price' => '1,299.00', 'compare_at' => null],
            3 => ['sku' => 'B-2', 'price' => '£5.00', 'compare_at' => '£7.00'],
        ], $rows);
    }

    public function test_detects_semicolon_delimiter_and_header_aliases(): void
    {
        // European Excel saves CSV with semicolons; Shopify exports call the column "Variant Price".
        $rows = $this->read("Variant SKU;Variant Price;RRP\nA-1;10.00;12.00\n");

        $this->assertSame(['sku' => 'A-1', 'price' => '10.00', 'compare_at' => '12.00'], $rows[2]);
    }

    public function test_keeps_real_line_numbers_and_skips_blank_lines(): void
    {
        $rows = $this->read("sku,price\nA-1,1.00\n\n,\nB-2,2.00\n");

        $this->assertSame([2, 5], array_keys($rows));
    }

    public function test_reports_missing_columns(): void
    {
        $this->expectException(CsvFormatException::class);
        $this->expectExceptionMessage('Missing column(s): price');

        $this->read("sku,cost\nA-1,1.00\n");
    }

    public function test_rejects_empty_file_and_header_only_file(): void
    {
        foreach (['', "sku,price\n"] as $content) {
            try {
                $this->read($content);
                $this->fail('Expected an exception for: '.json_encode($content));
            } catch (CsvFormatException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_limits_number_of_rows(): void
    {
        $this->expectException(CsvFormatException::class);
        $this->expectExceptionMessage('more than 2 rows');

        $this->read("sku,price\nA,1\nB,2\nC,3\n", maxRows: 2);
    }

    /**
     * @return array<int, array{sku: string, price: string, compare_at: ?string}>
     */
    private function read(string $content, int $maxRows = 100): array
    {
        $path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($path, $content);
        $this->files[] = $path;

        return iterator_to_array((new PriceCsvReader($maxRows))->read($path), true);
    }
}
