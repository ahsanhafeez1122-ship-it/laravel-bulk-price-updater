<?php

namespace App\Console\Commands;

use App\Exceptions\CsvFormatException;
use App\Exceptions\ImportStateException;
use App\Services\PriceImportService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * For scheduled supplier feeds, e.g. a nightly file dropped on SFTP:
 *   php artisan prices:import storage/feeds/prices.csv --apply
 */
#[Signature('prices:import {file : Path to the CSV file} {--apply : Apply the changes after the preview} {--confirm-flagged : Also apply changes above the threshold} {--threshold= : Flag changes over this percent}')]
#[Description('Preview (and optionally apply) a CSV of price changes')]
class ImportPrices extends Command
{
    public function handle(PriceImportService $service): int
    {
        $path = (string) $this->argument('file');
        if (! is_readable($path)) {
            $this->error("Can't read {$path}.");

            return self::FAILURE;
        }

        $threshold = (float) ($this->option('threshold') ?: config('prices.flag_threshold_pct'));

        try {
            $import = $service->preview($path, basename($path), null, $threshold);
        } catch (CsvFormatException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(['Rows', 'Will change', 'Large changes', 'No change', 'Errors'], [[
            $import->rows_total, $import->rows_changed, $import->rows_flagged, $import->rows_unchanged, $import->rows_error,
        ]]);
        $this->line("Preview saved as import #{$import->id}.");

        if (! $this->option('apply')) {
            $this->comment('Nothing changed. Re-run with --apply, or apply it in the web UI.');

            return self::SUCCESS;
        }

        try {
            $result = $service->apply($import, (bool) $this->option('confirm-flagged'));
        } catch (ImportStateException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Updated {$result['applied']} price(s), skipped {$result['skipped']}.");

        return $import->rows_error > 0 ? self::FAILURE : self::SUCCESS;
    }
}
