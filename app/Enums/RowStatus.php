<?php

namespace App\Enums;

enum RowStatus: string
{
    /** Price will change by less than the review threshold. */
    case Changed = 'changed';
    /** Price will change by more than the threshold, so someone must confirm it. */
    case Flagged = 'flagged';
    /** Same price as now; nothing to do. */
    case Unchanged = 'unchanged';
    /** Row can't be used: unknown SKU, bad price, duplicate, or changed since preview. */
    case Error = 'error';

    public function label(): string
    {
        return match ($this) {
            self::Changed => 'Will change',
            self::Flagged => 'Large change',
            self::Unchanged => 'No change',
            self::Error => 'Error',
        };
    }

    public function willApply(): bool
    {
        return $this === self::Changed || $this === self::Flagged;
    }
}
