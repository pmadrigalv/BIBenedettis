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
        Schema::create('unidades', function (Blueprint $table) {
            $table->bigIncrements('id_unidad');
            $table->string('nombre_unidad', 150);
            $table->foreignId('id_estado')->nullable()->constrained('estados', 'id_estado')->nullOnDelete();
            $table->string('ciudad')->nullable();
            $table->string('ip_unidad', 45)->nullable();
            $table->foreignId('id_zona')->nullable()->constrained('zonas', 'id_zona')->nullOnDelete();
            $table->foreignId('id_region')->nullable()->constrained('regiones', 'id_region')->nullOnDelete();
            $table->timestamp('uactip_unidad')->nullable();
            $table->date('fapertura_unidad')->nullable();
            $table->string('telefono_unidad', 25)->nullable();
            $table->foreignId('id_tipounidad')->nullable()->constrained('tipos_unidad', 'id_tipounidad')->nullOnDelete();
            $table->unsignedTinyInteger('status_unidad')->default(1);
            $table->unsignedInteger('alcancepedido_unidad')->nullable();
            $table->string('clave_unidad', 80)->nullable()->index();
            $table->timestamps();
        });
    }

    /** 
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unidades');
    }
};
