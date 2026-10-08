<?php

namespace App\Models;

use App\Enums\RowStatus;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Guarded(['id'])]
class PriceImportRow extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        // Integer casts matter: apply/rollback compare prices with !==, and MySQL can return strings.
        return [
            'line' => 'integer',
            'old_price_pence' => 'integer',
            'new_price_pence' => 'integer',
            'old_compare_at_pence' => 'integer',
            'new_compare_at_pence' => 'integer',
            'status' => RowStatus::class,
            'applied' => 'boolean',
            'rolled_back' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Percentage change in price, e.g. -12.5 for a 12.5% drop. Null when it can't be worked out.
     */
    public function changePercent(): ?float
    {
        if (! $this->old_price_pence || $this->new_price_pence === null) {
            return null;
        }

        return round((($this->new_price_pence - $this->old_price_pence) / $this->old_price_pence) * 100, 1);
    }
}
