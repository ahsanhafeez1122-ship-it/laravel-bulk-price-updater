<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only price history: one row per price change, from an import or a rollback.
 */
#[Guarded(['id'])]
class PriceChange extends Model
{
    public const UPDATED_AT = null;

    public const REASON_IMPORT = 'import';
    public const REASON_ROLLBACK = 'rollback';

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<PriceImport, $this>
     */
    public function priceImport(): BelongsTo
    {
        return $this->belongsTo(PriceImport::class);
    }
}
