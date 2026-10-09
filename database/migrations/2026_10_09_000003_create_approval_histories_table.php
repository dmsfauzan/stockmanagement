<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_histories', function (Blueprint $table): void {
            $table->id();
            $table->string('approvable_type', 100)->index();
            $table->unsignedBigInteger('approvable_id')->index();
            $table->unsignedTinyInteger('level');
            $table->foreignId('user_id')->constrained()->nullOnDelete();
            $table->string('action', 20)->index();
            $table->string('notes', 500)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['approvable_type', 'approvable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_histories');
    }
};
