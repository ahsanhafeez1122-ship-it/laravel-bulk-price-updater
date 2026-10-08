<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PriceImportApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Product::create(['sku' => 'RUG', 'name' => 'Rug', 'price_pence' => 14900]);
        Product::create(['sku' => 'LAMP', 'name' => 'Lamp', 'price_pence' => 5500]);
    }

    public function test_requires_a_token(): void
    {
        $this->postJson('/api/imports')->assertUnauthorized();
    }

    public function test_full_api_flow(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $created = $this->postJson('/api/imports', [
            'file' => UploadedFile::fake()->createWithContent('feed.csv', "sku,price\nRUG,139.00\nLAMP,99.00\nGHOST,1.00\n"),
        ])->assertCreated()
            ->assertJsonPath('data.status', 'previewed')
            ->assertJsonPath('data.needs_confirmation', true)
            ->assertJsonPath('data.counts.changed', 1)
            ->assertJsonPath('data.counts.flagged', 1)
            ->assertJsonPath('data.counts.error', 1);

        $id = $created->json('data.id');

        $this->getJson("/api/imports/{$id}/rows?status=error")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.sku', 'GHOST');

        $this->postJson("/api/imports/{$id}/apply")
            ->assertStatus(409)
            ->assertJsonFragment(['message' => '1 price(s) change by more than 30%. Confirm the large changes to apply this import.']);

        $this->postJson("/api/imports/{$id}/apply", ['confirm_flagged' => true])
            ->assertOk()
            ->assertJsonPath('data.applied', 2)
            ->assertJsonPath('data.import.status', 'applied');

        $this->postJson("/api/imports/{$id}/rollback")
            ->assertOk()
            ->assertJsonPath('data.restored', 2);

        $this->assertSame(14900, Product::firstWhere('sku', 'RUG')->price_pence);
    }

    public function test_validation_errors_are_json(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/imports', ['file' => UploadedFile::fake()->create('photo.png', 10, 'image/png')])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');
    }
}
