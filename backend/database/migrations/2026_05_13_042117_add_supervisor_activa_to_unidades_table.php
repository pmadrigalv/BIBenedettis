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
        Schema::table('unidades', function (Blueprint $table) {
            // From Tablero.unidad: supervisor (int FK to usuarios.id_usuario) and activa_unidad (tinyint flag)
            $table->unsignedBigInteger('supervisor')->nullable()->default(0)->after('fapertura_unidad');
            $table->tinyInteger('activa_unidad')->nullable()->after('supervisor');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('unidades', function (Blueprint $table) {
            $table->dropColumn(['supervisor', 'activa_unidad']);
        });
    }
};
