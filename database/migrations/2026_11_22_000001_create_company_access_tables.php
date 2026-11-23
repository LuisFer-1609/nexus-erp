<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auth_users', function (Blueprint $table) {
            $table->boolean('is_platform_admin')->default(false);
            $table->integer('company_id')->nullable();
            $table->integer('role_id')->nullable();
            $table->timestamp('password_set_at')->nullable();
            $table->foreign('company_id')->references('id')->on('companies');
            $table->foreign('role_id')->references('id')->on('roles');
        });

        DB::table('auth_users')->update(['password_set_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('auth_users', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropForeign(['role_id']);
            $table->dropColumn(['is_platform_admin', 'company_id', 'role_id', 'password_set_at']);
        });
    }
};
