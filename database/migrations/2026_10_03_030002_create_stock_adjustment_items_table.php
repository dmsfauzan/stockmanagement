<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_adjustment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_adjustment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->integer('system_quantity');
            $table->unsignedInteger('actual_quantity');
            $table->integer('difference');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index('stock_adjustment_id');
            $table->index('item_id');
            $table->unique(['stock_adjustment_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_adjustment_items');
    }
};
