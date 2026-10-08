<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\PriceImport
 */
class ImportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'file' => $this->original_filename,
            'status' => $this->status->value,
            'flag_threshold_pct' => $this->flag_threshold_pct,
            'needs_confirmation' => $this->needsConfirmation(),
            'counts' => [
                'total' => $this->rows_total,
                'changed' => $this->rows_changed,
                'flagged' => $this->rows_flagged,
                'unchanged' => $this->rows_unchanged,
                'error' => $this->rows_error,
                'applied' => $this->rows_applied,
                'skipped' => $this->rows_skipped,
            ],
            'created_at' => $this->created_at?->toIso8601String(),
            'applied_at' => $this->applied_at?->toIso8601String(),
            'rolled_back_at' => $this->rolled_back_at?->toIso8601String(),
            'links' => [
                'rows' => route('api.imports.rows', $this->resource),
            ],
        ];
    }
}
