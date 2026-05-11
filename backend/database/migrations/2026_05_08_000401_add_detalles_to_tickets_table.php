<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->date('fecha')->nullable()->after('titulo');
            $table->json('imagenes')->nullable()->after('usuario_id');
            $table->string('archivo_adjunto')->nullable()->after('imagenes');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['fecha', 'imagenes', 'archivo_adjunto']);
        });
    }
};
