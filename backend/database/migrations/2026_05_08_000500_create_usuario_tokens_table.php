<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuario_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_usuario')->constrained('usuarios', 'id_usuario')->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['id_usuario', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuario_tokens');
    }
};