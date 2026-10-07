<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods_issues', function (Blueprint $table): void {
            $table->foreignId('sales_order_id')->nullable()->after('customer_id')->constrained('sales_orders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('goods_issues', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('sales_order_id');
        });
    }
};
