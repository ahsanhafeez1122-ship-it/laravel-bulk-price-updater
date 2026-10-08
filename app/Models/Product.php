<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['sku', 'name', 'price_pence', 'compare_at_pence'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'price_pence' => 'integer',
            'compare_at_pence' => 'integer',
        ];
    }

    /**
     * @return HasMany<PriceChange, $this>
     */
    public function priceChanges(): HasMany
    {
        return $this->hasMany(PriceChange::class)->latest('id');
    }
}
