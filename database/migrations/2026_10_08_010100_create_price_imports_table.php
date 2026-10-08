<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('original_filename');
            $table->string('status', 20)->index();
            $table->decimal('flag_threshold_pct', 5, 2);
            $table->unsignedInteger('rows_total')->default(0);
            $table->unsignedInteger('rows_changed')->default(0);
            $table->unsignedInteger('rows_flagged')->default(0);
            $table->unsignedInteger('rows_unchanged')->default(0);
            $table->unsignedInteger('rows_error')->default(0);
            $table->unsignedInteger('rows_applied')->default(0);
            $table->unsignedInteger('rows_skipped')->default(0);
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('rolled_back_at')->nullable();
            $table->timestamps();
        });

        Schema::create('price_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_import_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('line');
            $table->string('sku', 64);
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('old_price_pence')->nullable();
            $table->unsignedInteger('new_price_pence')->nullable();
            $table->unsignedInteger('old_compare_at_pence')->nullable();
            $table->unsignedInteger('new_compare_at_pence')->nullable();
            $table->string('status', 20);
            $table->string('message')->nullable();
            $table->boolean('applied')->default(false);
            $table->boolean('rolled_back')->default(false);

            $table->index(['price_import_id', 'status']);
        });

        Schema::create('price_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('price_import_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('old_price_pence');
            $table->unsignedInteger('new_price_pence');
            $table->unsignedInteger('old_compare_at_pence')->nullable();
            $table->unsignedInteger('new_compare_at_pence')->nullable();
            $table->string('reason', 20);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['product_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_changes');
        Schema::dropIfExists('price_import_rows');
        Schema::dropIfExists('price_imports');
    }
};
