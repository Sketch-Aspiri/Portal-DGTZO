<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Identidad común con Dashboard y Tickets (plan de perfil único).
            $table->uuid('portal_user_id')->nullable()->unique()->after('id');
            $table->string('status', 20)->default('pending')->index()->after('password');
            $table->string('portal_role', 20)->default('usuario')->after('status');
            $table->string('avatar_path', 100)->nullable()->after('portal_role');
            $table->text('two_factor_secret')->nullable()->after('avatar_path');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
            $table->unsignedBigInteger('two_factor_last_timestamp')->nullable()->after('two_factor_confirmed_at');
            $table->foreignId('decided_by')->nullable()->after('two_factor_last_timestamp')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable()->after('decided_by');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('decided_by');
            $table->dropColumn([
                'portal_user_id', 'status', 'portal_role', 'avatar_path', 'two_factor_secret',
                'two_factor_recovery_codes', 'two_factor_confirmed_at', 'two_factor_last_timestamp', 'decided_at',
            ]);
        });
    }
};
