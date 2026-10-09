<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = [
        'goods_receipt_items',
        'goods_issue_items',
        'stock_transfer_items',
        'customer_return_items',
        'supplier_return_items',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->decimal('conversion_factor', 15, 6)->default(1)->after('unit_id');
                $table->unsignedInteger('base_quantity')->nullable()->after('conversion_factor');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->dropColumn(['conversion_factor', 'base_quantity']);
            });
        }
    }
};
