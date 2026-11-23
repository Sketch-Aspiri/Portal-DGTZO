<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuario_subsistema', function (Blueprint $table) {
            // Acceso quitado desde el portal: la fila se conserva (historial y reactivacion), pero ya no cuenta.
            $table->timestamp('revocado_at')->nullable()->after('aprovisionado_at');
            // Cambio que falta llevar al subsistema: actualizar | revocar | desactivar | reactivar.
            $table->string('accion_pendiente', 20)->nullable()->after('revocado_at');
        });
    }

    public function down(): void
    {
        Schema::table('usuario_subsistema', function (Blueprint $table) {
            $table->dropColumn(['revocado_at', 'accion_pendiente']);
        });
    }
};
