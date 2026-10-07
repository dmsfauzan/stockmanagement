<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods_receipts', function (Blueprint $table): void {
            $table->decimal('freight_cost', 15, 2)->default(0)->after('delivery_note');
            $table->decimal('other_cost', 15, 2)->default(0)->after('freight_cost');
            $table->string('landed_cost_method', 10)->default('value')->after('other_cost');
        });

        Schema::table('goods_receipt_items', function (Blueprint $table): void {
            $table->decimal('landed_cost', 15, 2)->default(0)->after('unit_cost');
        });
    }

    public function down(): void
    {
        Schema::table('goods_receipts', function (Blueprint $table): void {
            $table->dropColumn(['freight_cost', 'other_cost', 'landed_cost_method']);
        });

        Schema::table('goods_receipt_items', function (Blueprint $table): void {
            $table->dropColumn('landed_cost');
        });
    }
};
