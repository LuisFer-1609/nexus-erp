<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auth_users', function (Blueprint $table) {
            $table->integer('erp_user_id')->nullable()->unique();
            $table->foreign('erp_user_id')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::table('auth_users', function (Blueprint $table) {
            $table->dropForeign(['erp_user_id']);
            $table->dropUnique(['erp_user_id']);
            $table->dropColumn('erp_user_id');
        });
    }
};
