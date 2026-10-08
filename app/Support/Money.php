<?php

namespace App\Support;

/**
 * Converts between what people type in spreadsheets and integer pence.
 */
final class Money
{
    /** £10,000,000.00: anything bigger is almost certainly a typo or a pasted barcode. */
    public const MAX_PENCE = 1_000_000_000;

    /**
     * "12.99", "£12.99", "1,299.00", " 12 " → pence. Null when the value isn't a valid price.
     */
    public static function parsePence(?string $value): ?int
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        // Strip currency symbols/codes and spaces; keep digits, separators and a leading minus.
        $clean = preg_replace('/(GBP|EUR|USD|£|€|\$|\s|\x{00A0})/iu', '', $value);
        if ($clean === null || ! preg_match('/^\d{1,3}(,\d{3})*(\.\d{1,2})?$|^\d+(\.\d{1,2})?$/', $clean)) {
            return null;
        }

        [$pounds, $pence] = array_pad(explode('.', str_replace(',', '', $clean), 2), 2, '0');
        $total = ((int) $pounds * 100) + (int) str_pad($pence, 2, '0');

        return $total <= self::MAX_PENCE ? $total : null;
    }

    public static function format(?int $pence, string $symbol = '£'): string
    {
        if ($pence === null) {
            return '-';
        }

        return $symbol.number_format($pence / 100, 2);
    }
}
