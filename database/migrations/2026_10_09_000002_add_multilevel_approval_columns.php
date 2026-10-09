<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = [
        'goods_receipts',
        'goods_issues',
        'stock_adjustments',
        'stock_opnames',
        'stock_transfers',
        'purchase_orders',
        'sales_orders',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->unsignedTinyInteger('required_levels')->default(1)->after('status');
                $table->unsignedTinyInteger('current_level')->default(0)->after('required_levels');
                $table->decimal('approval_total', 17, 2)->default(0)->after('current_level');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->dropColumn(['required_levels', 'current_level', 'approval_total']);
            });
        }
    }
};
