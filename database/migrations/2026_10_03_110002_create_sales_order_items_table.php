<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sales_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('fulfilled_quantity')->default(0);
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['sales_order_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_order_items');
    }
};
