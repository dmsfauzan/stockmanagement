<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_boms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('kit_item_id')->constrained('items')->cascadeOnDelete();
            $table->foreignId('component_item_id')->constrained('items')->restrictOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['kit_item_id', 'component_item_id']);
        });

        Schema::create('assembly_orders', function (Blueprint $table): void {
            $table->id();
            $table->string('number', 40)->unique();
            $table->string('type', 20)->default('assembly');
            $table->date('assembly_date');
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reversal_reason')->nullable();
            $table->timestamps();

            $table->index(['status', 'warehouse_id']);
        });

        Schema::create('assembly_order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assembly_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->string('role', 20)->default('component');
            $table->unsignedInteger('quantity')->default(0);
            $table->decimal('unit_cost', 15, 4)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assembly_order_items');
        Schema::dropIfExists('assembly_orders');
        Schema::dropIfExists('item_boms');
    }
};
