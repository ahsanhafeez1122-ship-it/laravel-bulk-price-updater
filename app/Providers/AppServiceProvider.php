<?php

namespace App\Providers;

use App\Services\Csv\PriceCsvReader;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PriceCsvReader::class, fn () => new PriceCsvReader((int) config('prices.max_rows')));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
