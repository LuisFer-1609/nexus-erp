<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_general_ci';

            $table->integer('id', autoIncrement: true);
            $table->integer('company_id');
            $table->integer('role_id');
            $table->string('nickname', 100)->nullable();
            $table->string('name', 100);
            $table->string('last_name', 150)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('passwd', 255);
            $table->string('picture', 255)->nullable();
            $table->boolean('is_active')->default(1);
            $table->boolean('is_admin')->default(0);
            $table->dateTime('created_at')->useCurrent();
            $table->integer('created_user_id')->nullable();
            $table->dateTime('updated_at')->useCurrentOnUpdate()->nullable();
            $table->integer('updated_user_id')->nullable();
            $table->dateTime('deleted_at')->nullable();
            $table->integer('deleted_user_id')->nullable();
            $table->index(['company_id'], 'fk_users_company');
            $table->index(['role_id'], 'fk_users_role');
            $table->foreign('company_id', 'fk_users_company')->references('id')->on('companies');
            $table->foreign('role_id', 'fk_users_role')->references('id')->on('roles');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
