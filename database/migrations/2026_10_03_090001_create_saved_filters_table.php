<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_filters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('report', 80);
            $table->string('name', 80);
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'report', 'name'], 'saved_filter_unique');
            $table->index(['user_id', 'report']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_filters');
    }
};
