<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\PriceImportRow
 */
class ImportRowResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'line' => $this->line,
            'sku' => $this->sku,
            'status' => $this->status->value,
            'message' => $this->message,
            'old_price_pence' => $this->old_price_pence,
            'new_price_pence' => $this->new_price_pence,
            'old_compare_at_pence' => $this->old_compare_at_pence,
            'new_compare_at_pence' => $this->new_compare_at_pence,
            'change_pct' => $this->changePercent(),
            'applied' => $this->applied,
            'rolled_back' => $this->rolled_back,
        ];
    }
}
