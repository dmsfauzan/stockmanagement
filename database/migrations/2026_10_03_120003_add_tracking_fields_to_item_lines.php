<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods_receipt_items', function (Blueprint $table): void {
            $table->string('serial_number', 80)->nullable()->after('batch_number');
        });

        Schema::table('goods_issue_items', function (Blueprint $table): void {
            $table->string('batch_number', 60)->nullable()->after('location_id');
            $table->string('serial_number', 80)->nullable()->after('batch_number');
        });

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->string('serial_number', 80)->nullable()->after('batch_number');
        });
    }

    public function down(): void
    {
        Schema::table('goods_receipt_items', function (Blueprint $table): void {
            $table->dropColumn('serial_number');
        });

        Schema::table('goods_issue_items', function (Blueprint $table): void {
            $table->dropColumn(['batch_number', 'serial_number']);
        });

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropColumn('serial_number');
        });
    }
};
