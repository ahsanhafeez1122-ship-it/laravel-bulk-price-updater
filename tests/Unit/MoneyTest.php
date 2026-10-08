<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    /**
     * @return array<string, array{string, ?int}>
     */
    public static function prices(): array
    {
        return [
            'plain' => ['12.99', 1299],
            'whole pounds' => ['12', 1200],
            'one decimal' => ['12.5', 1250],
            'pound sign' => ['£12.99', 1299],
            'thousands separator' => ['1,299.00', 129900],
            'spaces' => ['  £ 49.50 ', 4950],
            'currency code' => ['GBP 10.00', 1000],
            'zero' => ['0', 0],
            'text' => ['twelve', null],
            'negative' => ['-5.00', null],
            'three decimals' => ['12.999', null],
            'bad thousands' => ['12,99', null],
            'empty' => ['', null],
            'absurdly large' => ['99999999.00', null],
        ];
    }

    #[DataProvider('prices')]
    public function test_parses_spreadsheet_prices(string $input, ?int $expected): void
    {
        $this->assertSame($expected, Money::parsePence($input));
    }

    public function test_formats_pence(): void
    {
        $this->assertSame('£1,299.00', Money::format(129900));
        $this->assertSame('£0.99', Money::format(99));
        $this->assertSame('-', Money::format(null));
    }
}
