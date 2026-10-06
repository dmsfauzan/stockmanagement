<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->decimal('unit_cost', 15, 4)->nullable()->after('balance_after');
            $table->decimal('total_cost', 17, 2)->default(0)->after('unit_cost');
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropColumn(['unit_cost', 'total_cost']);
        });
    }
};
