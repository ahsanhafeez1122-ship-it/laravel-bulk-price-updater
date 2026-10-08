<?php

namespace App\Models;

use App\Enums\ImportStatus;
use App\Enums\RowStatus;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Guarded(['id'])]
class PriceImport extends Model
{
    protected function casts(): array
    {
        return [
            'status' => ImportStatus::class,
            'flag_threshold_pct' => 'float',
            'applied_at' => 'datetime',
            'rolled_back_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<PriceImportRow, $this>
     */
    public function rows(): HasMany
    {
        return $this->hasMany(PriceImportRow::class)->orderBy('line');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function needsConfirmation(): bool
    {
        return $this->status === ImportStatus::Previewed && $this->rows_flagged > 0;
    }

    public function canApply(): bool
    {
        return $this->status === ImportStatus::Previewed && ($this->rows_changed + $this->rows_flagged) > 0;
    }

    public function canRollBack(): bool
    {
        return $this->status === ImportStatus::Applied && $this->rows_applied > 0;
    }

    /**
     * Recalculate the preview counters from the rows.
     */
    public function refreshCounts(): void
    {
        $counts = $this->rows()->reorder()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $this->forceFill([
            'rows_total' => (int) $counts->sum(),
            'rows_changed' => (int) ($counts[RowStatus::Changed->value] ?? 0),
            'rows_flagged' => (int) ($counts[RowStatus::Flagged->value] ?? 0),
            'rows_unchanged' => (int) ($counts[RowStatus::Unchanged->value] ?? 0),
            'rows_error' => (int) ($counts[RowStatus::Error->value] ?? 0),
        ])->save();
    }
}
