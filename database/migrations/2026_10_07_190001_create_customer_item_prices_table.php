<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_item_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('min_quantity')->default(1);
            $table->decimal('price', 15, 2)->default(0);
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->unique(['customer_id', 'item_id', 'min_quantity']);
            $table->index(['customer_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_item_prices');
    }
};
