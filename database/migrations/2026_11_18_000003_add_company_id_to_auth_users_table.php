<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Empresa a la que pertenece cada usuario de inicio de sesión. Nexus IA la
     * usa para responder únicamente con los datos de esa empresa.
     */
    public function up(): void
    {
        Schema::table('auth_users', function (Blueprint $table) {
            $table->integer('company_id')->nullable()->after('email')->index('ix_auth_users_company');
        });

        // SQLite no permite añadir llaves foráneas a una tabla existente.
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::table('auth_users', function (Blueprint $table) {
                $table->foreign('company_id', 'fk_auth_users_company')
                    ->references('id')->on('companies')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('auth_users', function (Blueprint $table) {
            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                $table->dropForeign('fk_auth_users_company');
            }
            $table->dropIndex('ix_auth_users_company');
            $table->dropColumn('company_id');
        });
    }
};
