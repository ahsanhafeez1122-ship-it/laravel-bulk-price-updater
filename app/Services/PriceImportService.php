<?php

namespace App\Services;

use App\Enums\ImportStatus;
use App\Enums\RowStatus;
use App\Exceptions\ImportStateException;
use App\Models\PriceChange;
use App\Models\PriceImport;
use App\Models\PriceImportRow;
use App\Models\Product;
use App\Models\User;
use App\Services\Csv\PriceCsvReader;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Preview → apply → (optional) roll back, for bulk price changes from a CSV.
 *
 * Nothing touches live prices until someone has seen the preview and applied it.
 * Apply and rollback each run in one transaction with the products locked, so a half-applied
 * file is impossible, and prices edited by someone else after the preview are never overwritten.
 */
class PriceImportService
{
    private const CHUNK = 500;

    public function __construct(private readonly PriceCsvReader $reader)
    {
    }

    public function preview(string $path, string $filename, ?User $user, float $flagThresholdPct): PriceImport
    {
        // Read and validate the whole file first, so a broken file never leaves a half-built import.
        $parsed = iterator_to_array($this->reader->read($path), true);

        return DB::transaction(function () use ($parsed, $filename, $user, $flagThresholdPct) {
            $import = PriceImport::create([
                'user_id' => $user?->id,
                'original_filename' => mb_substr($filename, 0, 255),
                'status' => ImportStatus::Previewed,
                'flag_threshold_pct' => $flagThresholdPct,
            ]);

            $products = Product::query()
                ->whereIn('sku', array_unique(array_column($parsed, 'sku')))
                ->get()
                ->keyBy('sku');

            $seen = [];
            $rows = [];
            foreach ($parsed as $line => $data) {
                $rows[] = $this->buildRow($import, (int) $line, $data, $products->get($data['sku']), $seen, $flagThresholdPct);
                if (count($rows) >= self::CHUNK) {
                    PriceImportRow::insert($rows);
                    $rows = [];
                }
            }
            if ($rows !== []) {
                PriceImportRow::insert($rows);
            }

            $import->refreshCounts();

            return $import;
        });
    }

    /**
     * @return array{applied: int, skipped: int}
     */
    public function apply(PriceImport $import, bool $confirmFlagged): array
    {
        return DB::transaction(function () use ($import, $confirmFlagged) {
            $import = PriceImport::query()->lockForUpdate()->findOrFail($import->id);

            if ($import->status !== ImportStatus::Previewed) {
                throw new ImportStateException("This import can't be applied because it is {$import->status->label()}.");
            }
            if ($import->rows_flagged > 0 && ! $confirmFlagged) {
                throw new ImportStateException(sprintf(
                    '%d price(s) change by more than %s%%. Confirm the large changes to apply this import.',
                    $import->rows_flagged,
                    rtrim(rtrim(number_format($import->flag_threshold_pct, 2), '0'), '.')
                ));
            }

            $rows = $import->rows()
                ->whereIn('status', [RowStatus::Changed, RowStatus::Flagged])
                ->get();
            $products = Product::query()
                ->whereIn('id', $rows->pluck('product_id')->filter())
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $applied = 0;
            $skipped = 0;
            foreach ($rows as $row) {
                $product = $products->get($row->product_id);

                // Someone changed this price after the preview: keep their change, don't overwrite it.
                if (! $product || $product->price_pence !== $row->old_price_pence || $product->compare_at_pence !== $row->old_compare_at_pence) {
                    $row->update(['status' => RowStatus::Error, 'message' => 'Price changed after the preview, so it was skipped. Upload the file again to update it.']);
                    $skipped++;
                    continue;
                }

                $this->changePrice($product, $row->new_price_pence, $row->new_compare_at_pence, $import, PriceChange::REASON_IMPORT);
                $row->update(['applied' => true]);
                $applied++;
            }

            $import->forceFill([
                'status' => ImportStatus::Applied,
                'applied_at' => now(),
                'rows_applied' => $applied,
                'rows_skipped' => $skipped,
            ])->save();

            return ['applied' => $applied, 'skipped' => $skipped];
        });
    }

    /**
     * @return array{restored: int, conflicts: int}
     */
    public function rollback(PriceImport $import): array
    {
        return DB::transaction(function () use ($import) {
            $import = PriceImport::query()->lockForUpdate()->findOrFail($import->id);

            if ($import->status !== ImportStatus::Applied) {
                throw new ImportStateException("Only applied imports can be rolled back. This one is {$import->status->label()}.");
            }

            $rows = $import->rows()->where('applied', true)->where('rolled_back', false)->get();
            $products = Product::query()
                ->whereIn('id', $rows->pluck('product_id')->filter())
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $restored = 0;
            $conflicts = 0;
            foreach ($rows as $row) {
                $product = $products->get($row->product_id);

                // Price changed again since this import; restoring would wipe out the newer change.
                if (! $product || $product->price_pence !== $row->new_price_pence || $product->compare_at_pence !== $row->new_compare_at_pence) {
                    $row->update(['message' => 'Not restored: the price was changed again after this import.']);
                    $conflicts++;
                    continue;
                }

                $this->changePrice($product, $row->old_price_pence, $row->old_compare_at_pence, $import, PriceChange::REASON_ROLLBACK);
                $row->update(['rolled_back' => true]);
                $restored++;
            }

            $import->forceFill(['status' => ImportStatus::RolledBack, 'rolled_back_at' => now()])->save();

            return ['restored' => $restored, 'conflicts' => $conflicts];
        });
    }

    public function discard(PriceImport $import): void
    {
        if ($import->status !== ImportStatus::Previewed) {
            throw new ImportStateException('Only imports that are waiting for review can be discarded.');
        }

        $import->forceFill(['status' => ImportStatus::Discarded])->save();
    }

    /**
     * @param  array{sku: string, price: string, compare_at: ?string}  $data
     * @param  array<string, int>  $seen  sku => first line, shared across rows to catch duplicates
     * @return array<string, mixed>
     */
    private function buildRow(PriceImport $import, int $line, array $data, ?Product $product, array &$seen, float $threshold): array
    {
        $row = [
            'price_import_id' => $import->id,
            'line' => $line,
            'sku' => mb_substr($data['sku'], 0, 64),
            'product_id' => $product?->id,
            'old_price_pence' => $product?->price_pence,
            'old_compare_at_pence' => $product?->compare_at_pence,
            'new_price_pence' => null,
            'new_compare_at_pence' => null,
            'status' => RowStatus::Error->value,
            'message' => null,
            'applied' => false,
            'rolled_back' => false,
        ];

        if ($data['sku'] === '') {
            return ['message' => 'SKU is empty.'] + $row;
        }
        if (isset($seen[$data['sku']])) {
            return ['message' => "Duplicate SKU, already on line {$seen[$data['sku']]}."] + $row;
        }
        $seen[$data['sku']] = $line;

        if ($product === null) {
            return ['message' => 'No product with this SKU.'] + $row;
        }

        $newPrice = Money::parsePence($data['price']);
        if ($newPrice === null || $newPrice === 0) {
            return ['message' => "\"{$data['price']}\" is not a valid price."] + $row;
        }

        $newCompare = null;
        if ($data['compare_at'] !== null) {
            $newCompare = Money::parsePence($data['compare_at']);
            if ($newCompare === null) {
                return ['message' => "\"{$data['compare_at']}\" is not a valid compare-at price."] + $row;
            }
            if ($newCompare <= $newPrice) {
                return ['message' => 'Compare-at price must be higher than the price, or left empty.'] + $row;
            }
        }

        $row['new_price_pence'] = $newPrice;
        $row['new_compare_at_pence'] = $newCompare;

        if ($newPrice === $product->price_pence && $newCompare === $product->compare_at_pence) {
            $row['status'] = RowStatus::Unchanged->value;

            return $row;
        }

        $changePct = abs(($newPrice - $product->price_pence) / max(1, $product->price_pence) * 100);
        if ($changePct > $threshold) {
            $row['status'] = RowStatus::Flagged->value;
            $row['message'] = sprintf('Price changes by %s%%.', number_format($changePct, 1));

            return $row;
        }

        $row['status'] = RowStatus::Changed->value;

        return $row;
    }

    private function changePrice(Product $product, int $price, ?int $compareAt, PriceImport $import, string $reason): void
    {
        PriceChange::create([
            'product_id' => $product->id,
            'price_import_id' => $import->id,
            'old_price_pence' => $product->price_pence,
            'new_price_pence' => $price,
            'old_compare_at_pence' => $product->compare_at_pence,
            'new_compare_at_pence' => $compareAt,
            'reason' => $reason,
        ]);

        $product->forceFill(['price_pence' => $price, 'compare_at_pence' => $compareAt])->save();
    }
}
