<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuario_subsistema', function (Blueprint $table) {
            // Dónde se ubica a la persona en el subsistema: equipos y coordinaciones (Tickets), estación (Dashboard).
            $table->json('contexto')->nullable()->after('rol');
            $table->unsignedTinyInteger('intentos')->default(0)->after('aprovisionado_at');
        });
    }

    public function down(): void
    {
        Schema::table('usuario_subsistema', function (Blueprint $table) {
            $table->dropColumn(['contexto', 'intentos']);
        });
    }
};
