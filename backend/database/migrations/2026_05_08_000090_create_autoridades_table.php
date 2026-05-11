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
        Schema::create('autoridades', function (Blueprint $table) {
            $table->bigIncrements('id_autoridad');
            $table->enum('msg_autoridad', ['Y', 'N'])->default('N');
            $table->string('descripcion_autoridad', 80);
            $table->enum('tipo_autoridad', ['U', 'C']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('autoridades');
    }
};
