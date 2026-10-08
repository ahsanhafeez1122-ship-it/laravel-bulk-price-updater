<?php

namespace App\Enums;

enum ImportStatus: string
{
    case Previewed = 'previewed';
    case Applied = 'applied';
    case RolledBack = 'rolled_back';
    case Discarded = 'discarded';

    public function label(): string
    {
        return match ($this) {
            self::Previewed => 'Waiting for review',
            self::Applied => 'Applied',
            self::RolledBack => 'Rolled back',
            self::Discarded => 'Discarded',
        };
    }
}
