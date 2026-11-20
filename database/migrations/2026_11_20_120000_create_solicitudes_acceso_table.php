<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitudes_acceso', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('puesto', 120);
            $table->string('ubicacion', 120);
            $table->string('telefono', 30)->nullable();
            $table->text('funciones');
            $table->text('justificacion')->nullable();
            // Misma forma que usuario_subsistema.contexto: [subsistema => [rol, team_ids, coordination_ids, estacion_id, area, detalle]].
            $table->json('accesos');
            $table->string('estado', 20)->default('enviada')->index();
            $table->timestamp('enviada_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes_acceso');
    }
};
