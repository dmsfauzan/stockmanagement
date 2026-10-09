<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('stock_balances', 'quantity_quarantine')) {
            Schema::table('stock_balances', function (Blueprint $table): void {
                $table->unsignedInteger('quantity_quarantine')->default(0)->after('quantity_reserved');
            });
        }

        if (! Schema::hasColumn('stock_lots', 'quality_status')) {
            Schema::table('stock_lots', function (Blueprint $table): void {
                $table->string('quality_status', 20)->default('good')->after('quantity');
                $table->index(['item_id', 'quality_status']);
            });
        }

        if (! Schema::hasColumn('goods_receipts', 'requires_inspection')) {
            Schema::table('goods_receipts', function (Blueprint $table): void {
                $table->boolean('requires_inspection')->default(false)->after('landed_cost_method');
            });
        }

        if (! Schema::hasColumn('stock_movements', 'quality_status')) {
            Schema::table('stock_movements', function (Blueprint $table): void {
                $table->string('quality_status', 20)->default('good')->after('serial_number');
            });
        }

        if (Schema::hasTable('stock_movement_archives') && ! Schema::hasColumn('stock_movement_archives', 'quality_status')) {
            Schema::table('stock_movement_archives', function (Blueprint $table): void {
                $table->string('quality_status', 20)->default('good')->after('serial_number');
            });
        }

        if (Schema::getConnection()->getDriverName() === 'mysql' && Schema::hasColumn('stock_balances', 'quantity_available')) {
            DB::statement('ALTER TABLE stock_balances DROP COLUMN quantity_available');
            DB::statement('ALTER TABLE stock_balances ADD COLUMN quantity_available INT AS (quantity_on_hand - quantity_reserved - quantity_quarantine) STORED');
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql' && Schema::hasColumn('stock_balances', 'quantity_available')) {
            DB::statement('ALTER TABLE stock_balances DROP COLUMN quantity_available');
            DB::statement('ALTER TABLE stock_balances ADD COLUMN quantity_available INT AS (quantity_on_hand - quantity_reserved) STORED');
        }

        if (Schema::hasColumn('goods_receipts', 'requires_inspection')) {
            Schema::table('goods_receipts', function (Blueprint $table): void {
                $table->dropColumn('requires_inspection');
            });
        }

        if (Schema::hasColumn('stock_movements', 'quality_status')) {
            Schema::table('stock_movements', function (Blueprint $table): void {
                $table->dropColumn('quality_status');
            });
        }

        if (Schema::hasColumn('stock_lots', 'quality_status')) {
            Schema::table('stock_lots', function (Blueprint $table): void {
                $table->dropIndex(['item_id', 'quality_status']);
                $table->dropColumn('quality_status');
            });
        }

        if (Schema::hasColumn('stock_balances', 'quantity_quarantine')) {
            Schema::table('stock_balances', function (Blueprint $table): void {
                $table->dropColumn('quantity_quarantine');
            });
        }
    }
};
