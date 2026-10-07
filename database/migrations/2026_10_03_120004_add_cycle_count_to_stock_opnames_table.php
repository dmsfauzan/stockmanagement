<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_opnames', function (Blueprint $table): void {
            $table->string('type', 10)->default('full')->after('location_id');
            $table->foreignId('zone_id')->nullable()->after('type')->constrained('zones')->nullOnDelete();
            $table->foreignId('rack_id')->nullable()->after('zone_id')->constrained('racks')->nullOnDelete();
            $table->date('scheduled_date')->nullable()->after('rack_id');
        });
    }

    public function down(): void
    {
        Schema::table('stock_opnames', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('rack_id');
            $table->dropConstrainedForeignId('zone_id');
            $table->dropColumn(['type', 'scheduled_date']);
        });
    }
};
