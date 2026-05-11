<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->unsignedBigInteger('tecnico_id')
                ->nullable()
                ->after('usuario_id');

            $table->foreign('tecnico_id')
                ->references('id_usuario')
                ->on('usuarios')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeign(['tecnico_id']);
            $table->dropColumn('tecnico_id');
        });
    }
};
