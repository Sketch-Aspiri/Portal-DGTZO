<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendario_eventos', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 20);
            $table->string('titulo', 120);
            $table->text('descripcion')->nullable();
            $table->string('lugar', 120)->nullable();
            // Instantes en UTC; la zona de la persona (America/Cancun) se aplica al mostrar y capturar.
            $table->timestamp('inicia_at');
            $table->timestamp('termina_at')->nullable();
            $table->boolean('todo_el_dia')->default(false);
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('actualizado_por')->nullable()->constrained('users')->nullOnDelete();
            // Control de avisos: al publicar y el recordatorio de un día antes (una sola vez cada uno).
            $table->timestamp('avisado_at')->nullable();
            $table->timestamp('recordado_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('inicia_at');
            $table->index(['tipo', 'inicia_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendario_eventos');
    }
};
