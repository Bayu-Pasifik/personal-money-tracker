<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advisory_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('source', ['telegram', 'web']);
            $table->json('messages');
            $table->timestamps();

            $table->index(['user_id', 'source', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advisory_sessions');
    }
};
