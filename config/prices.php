<?php

return [
    /*
     * Price changes bigger than this (in percent, up or down) are flagged in the preview
     * and need an explicit confirmation before the import can be applied.
     */
    'flag_threshold_pct' => (float) env('PRICES_FLAG_THRESHOLD_PCT', 30),

    /*
     * Largest number of rows accepted in one file.
     */
    'max_rows' => (int) env('PRICES_MAX_ROWS', 10000),
];
