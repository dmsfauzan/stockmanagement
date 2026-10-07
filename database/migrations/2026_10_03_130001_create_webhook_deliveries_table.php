<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->string('event', 80);
            $table->string('url', 500);
            $table->json('payload')->nullable();
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->boolean('success')->default(false);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['event', 'created_at']);
            $table->index('success');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
    }
};
