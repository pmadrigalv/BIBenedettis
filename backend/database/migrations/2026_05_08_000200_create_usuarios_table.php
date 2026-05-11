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
        Schema::create('usuarios', function (Blueprint $table) {
            $table->bigIncrements('id_usuario');
            $table->string('uid_usuario', 80)->unique();
            $table->string('pwd_usuario');
            $table->string('nombres_usuario', 120);
            $table->string('apellidos_usuario', 120);
            $table->string('telefono_usuario', 25)->nullable();
            $table->string('email_usuario', 150)->unique();
            $table->foreignId('id_autoridad')->nullable()->constrained('autoridades', 'id_autoridad')->nullOnDelete();
            $table->boolean('vigencia_usuario')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usuarios');
    }
};
