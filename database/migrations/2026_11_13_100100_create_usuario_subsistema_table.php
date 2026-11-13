<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuario_subsistema', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('subsistema', 40);
            $table->string('rol', 40);
            // Estado del aprovisionamiento en el subsistema (fase 3 del plan).
            $table->string('aprovisionamiento', 20)->default('pendiente');
            $table->timestamp('aprovisionado_at')->nullable();
            $table->string('ultimo_error', 255)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'subsistema']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuario_subsistema');
    }
};
