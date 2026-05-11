<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('unidad_usuario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_unidad')->constrained('unidades', 'id_unidad')->cascadeOnDelete();
            $table->foreignId('id_usuario')->constrained('usuarios', 'id_usuario')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['id_unidad', 'id_usuario']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unidad_usuario');
    }
};
