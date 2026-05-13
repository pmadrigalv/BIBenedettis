<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            // Drop unique index on email first
            $table->dropUnique(['email_usuario']);
            // Make email nullable
            $table->string('email_usuario', 150)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->string('email_usuario', 150)->nullable(false)->change();
            $table->unique('email_usuario');
        });
    }
};
