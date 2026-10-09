<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batch_recalls', function (Blueprint $table): void {
            $table->id();
            $table->string('batch_number', 80);
            $table->string('serial_number', 80)->nullable();
            $table->string('type', 20)->default('batch');
            $table->text('reason');
            $table->string('status', 20)->default('active');
            $table->foreignId('recalled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('recalled_at')->nullable();
            $table->foreignId('lifted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('lifted_at')->nullable();
            $table->timestamps();

            $table->unique(['batch_number', 'serial_number']);
            $table->index(['status', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batch_recalls');
    }
};
