<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name');
            $table->string('symbol', 10)->nullable();
            $table->boolean('is_base')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('exchange_rates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('currency_id')->constrained()->cascadeOnDelete();
            $table->date('effective_date');
            $table->decimal('rate', 18, 8);
            $table->timestamps();

            $table->unique(['currency_id', 'effective_date']);
        });

        foreach (['purchase_orders', 'sales_orders'] as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->string('currency_code', 10)->default('IDR')->after('id');
                $table->decimal('exchange_rate', 18, 8)->default(1)->after('currency_code');
            });
        }

        DB::table('currencies')->insert([
            ['code' => 'IDR', 'name' => 'Rupiah Indonesia', 'symbol' => 'Rp', 'is_base' => true, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'USD', 'name' => 'Dolar AS', 'symbol' => '$', 'is_base' => false, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        foreach (['purchase_orders', 'sales_orders'] as $table) {
            if (Schema::hasColumn($table, 'currency_code')) {
                Schema::table($table, function (Blueprint $table): void {
                    $table->dropColumn(['currency_code', 'exchange_rate']);
                });
            }
        }

        Schema::dropIfExists('exchange_rates');
        Schema::dropIfExists('currencies');
    }
};
