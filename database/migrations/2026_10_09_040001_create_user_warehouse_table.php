<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_warehouse', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'warehouse_id']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('all_warehouses')->default(true)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('all_warehouses');
        });

        Schema::dropIfExists('user_warehouse');
    }
};
