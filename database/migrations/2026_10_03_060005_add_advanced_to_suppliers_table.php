<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table): void {
            $table->unsignedInteger('lead_time_days')->default(7)->after('status');
            $table->string('payment_terms', 40)->default('NET 30')->after('lead_time_days');
            $table->string('region', 80)->nullable()->after('payment_terms');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table): void {
            $table->dropColumn(['lead_time_days', 'payment_terms', 'region']);
        });
    }
};
