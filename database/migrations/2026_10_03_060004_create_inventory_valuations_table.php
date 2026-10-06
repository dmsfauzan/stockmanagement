<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_valuations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->integer('quantity')->default(0);
            $table->decimal('average_cost', 15, 4)->default(0);
            $table->decimal('total_value', 17, 2)->default(0);
            $table->timestamps();
            $table->unique(['item_id', 'warehouse_id']);
            $table->index('warehouse_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_valuations');
    }
};
