<?php

namespace Tests\Feature;

use App\Enums\ImportStatus;
use App\Enums\RowStatus;
use App\Exceptions\ImportStateException;
use App\Models\PriceChange;
use App\Models\Product;
use App\Models\User;
use App\Services\PriceImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class PriceImportFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Product::create(['sku' => 'SOFA', 'name' => 'Sofa', 'price_pence' => 89900]);
        Product::create(['sku' => 'BED', 'name' => 'Bed', 'price_pence' => 54900, 'compare_at_pence' => 64900]);
        Product::create(['sku' => 'LAMP', 'name' => 'Lamp', 'price_pence' => 5500]);
        Product::create(['sku' => 'RUG', 'name' => 'Rug', 'price_pence' => 14900]);
    }

    public function test_preview_classifies_every_row_without_changing_prices(): void
    {
        $import = $this->preview(implode("\n", [
            'sku,price,compare_at_price',
            'SOFA,799.00,899.00',   // -11%: changed
            'BED,549.00,649.00',    // same: unchanged
            'LAMP,99.00,',          // +80%: flagged
            'RUG,abc,',             // bad price
            'GHOST,10.00,',         // unknown sku
            'SOFA,700.00,',         // duplicate
            'BED,500.00,450.00',    // compare-at below price (duplicate wins first, but still an error)
        ]));

        $this->assertSame(ImportStatus::Previewed, $import->status);
        $this->assertSame([7, 1, 1, 1, 4], [$import->rows_total, $import->rows_changed, $import->rows_flagged, $import->rows_unchanged, $import->rows_error]);
        $this->assertSame(89900, Product::firstWhere('sku', 'SOFA')->price_pence, 'preview must not touch live prices');

        $messages = $import->rows()->where('status', RowStatus::Error)->pluck('message', 'line');
        $this->assertStringContainsString('not a valid price', $messages[5]);
        $this->assertSame('No product with this SKU.', $messages[6]);
        $this->assertSame('Duplicate SKU, already on line 2.', $messages[7]);
    }

    public function test_apply_updates_prices_and_writes_history(): void
    {
        $import = $this->preview("sku,price,compare_at_price\nSOFA,799.00,899.00\nRUG,139.00,\n");

        $result = app(PriceImportService::class)->apply($import, false);

        $this->assertSame(['applied' => 2, 'skipped' => 0], $result);
        $sofa = Product::firstWhere('sku', 'SOFA');
        $this->assertSame([79900, 89900], [$sofa->price_pence, $sofa->compare_at_pence]);
        $this->assertDatabaseHas('price_changes', [
            'product_id' => $sofa->id, 'old_price_pence' => 89900, 'new_price_pence' => 79900, 'reason' => PriceChange::REASON_IMPORT,
        ]);
        $this->assertSame(ImportStatus::Applied, $import->refresh()->status);
    }

    public function test_large_changes_need_confirmation(): void
    {
        $import = $this->preview("sku,price\nLAMP,99.00\n");

        try {
            app(PriceImportService::class)->apply($import, false);
            $this->fail('Expected confirmation to be required');
        } catch (ImportStateException $e) {
            $this->assertStringContainsString('more than 30%', $e->getMessage());
        }
        $this->assertSame(5500, Product::firstWhere('sku', 'LAMP')->price_pence);

        app(PriceImportService::class)->apply($import, true);
        $this->assertSame(9900, Product::firstWhere('sku', 'LAMP')->price_pence);
    }

    public function test_price_edited_after_preview_is_not_overwritten(): void
    {
        $import = $this->preview("sku,price\nSOFA,799.00\nRUG,139.00\n");
        Product::where('sku', 'SOFA')->update(['price_pence' => 85000]); // someone edits it in the meantime

        $result = app(PriceImportService::class)->apply($import, false);

        $this->assertSame(['applied' => 1, 'skipped' => 1], $result);
        $this->assertSame(85000, Product::firstWhere('sku', 'SOFA')->price_pence);
        $this->assertSame(13900, Product::firstWhere('sku', 'RUG')->price_pence);
    }

    public function test_import_cannot_be_applied_twice(): void
    {
        $import = $this->preview("sku,price\nRUG,139.00\n");
        app(PriceImportService::class)->apply($import, false);

        $this->expectException(ImportStateException::class);
        app(PriceImportService::class)->apply($import, false);
    }

    public function test_rollback_restores_old_prices_but_keeps_newer_edits(): void
    {
        $import = $this->preview("sku,price\nSOFA,799.00\nRUG,139.00\n");
        app(PriceImportService::class)->apply($import, false);
        Product::where('sku', 'RUG')->update(['price_pence' => 12000]); // changed again later

        $result = app(PriceImportService::class)->rollback($import);

        $this->assertSame(['restored' => 1, 'conflicts' => 1], $result);
        $this->assertSame(89900, Product::firstWhere('sku', 'SOFA')->price_pence);
        $this->assertSame(12000, Product::firstWhere('sku', 'RUG')->price_pence);
        $this->assertSame(ImportStatus::RolledBack, $import->refresh()->status);
        $this->assertSame(1, PriceChange::where('reason', PriceChange::REASON_ROLLBACK)->count());
    }

    public function test_web_flow_upload_preview_apply(): void
    {
        $file = UploadedFile::fake()->createWithContent('prices.csv', "sku,price\nRUG,139.00\n");

        $response = $this->actingAs($this->user)->post('/imports', ['file' => $file]);
        $import = \App\Models\PriceImport::first();
        $response->assertRedirect(route('imports.show', $import));

        $this->actingAs($this->user)->get(route('imports.show', $import))
            ->assertOk()
            ->assertSee('Apply 1 price change(s)')
            ->assertSee('£149.00')
            ->assertSee('£139.00');

        $this->actingAs($this->user)->post(route('imports.apply', $import))
            ->assertRedirect(route('imports.show', $import))
            ->assertSessionHas('status', 'Updated 1 price(s).');
    }

    public function test_web_rejects_unreadable_file_with_clear_message(): void
    {
        $file = UploadedFile::fake()->createWithContent('prices.csv', "code,amount\nRUG,139.00\n");

        $this->actingAs($this->user)->from('/imports')->post('/imports', ['file' => $file])
            ->assertRedirect('/imports')
            ->assertSessionHasErrors(['file' => 'Missing column(s): sku, price. The first row must have headers, e.g. "sku,price,compare_at_price".']);
    }

    public function test_error_rows_download_as_csv(): void
    {
        $import = $this->preview("sku,price\nGHOST,1.00\nRUG,abc\n");

        $csv = $this->actingAs($this->user)->get(route('imports.errors', $import))->assertOk()->streamedContent();

        $this->assertStringContainsString('GHOST', $csv);
        $this->assertStringContainsString('No product with this SKU.', $csv);
    }

    public function test_pages_require_login(): void
    {
        $this->get('/imports')->assertUnauthorized();
    }

    private function preview(string $csv): \App\Models\PriceImport
    {
        $path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($path, $csv);

        try {
            return app(PriceImportService::class)->preview($path, 'test.csv', $this->user, 30);
        } finally {
            unlink($path);
        }
    }
}
