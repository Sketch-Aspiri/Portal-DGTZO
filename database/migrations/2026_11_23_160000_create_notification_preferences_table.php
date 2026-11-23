<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Silenciado del aviso al celular por sistema. Sin fila = activo. El aviso siempre queda en el centro de
     * notificaciones; solo se omite el push.
     */
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('subsistema', 30);
            $table->boolean('muted')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'subsistema']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
