<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_item_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->decimal('price', 15, 2)->default(0);
            $table->unsignedInteger('lead_time_days')->default(7);
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->unique(['supplier_id', 'item_id'], 'sup_item_unique');
            $table->index('item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_item_prices');
    }
};
