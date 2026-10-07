<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_lots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->string('batch_number', 60)->nullable();
            $table->string('serial_number', 80)->nullable();
            $table->date('expiry_date')->nullable();
            $table->unsignedInteger('quantity')->default(0);
            $table->decimal('unit_cost', 15, 4)->default(0);
            $table->timestamps();

            $table->unique(['item_id', 'warehouse_id', 'location_id', 'batch_number', 'serial_number'], 'stock_lot_unique');
            $table->index(['item_id', 'expiry_date']);
            $table->index(['warehouse_id', 'location_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_lots');
    }
};
