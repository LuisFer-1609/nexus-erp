<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nationalities', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_general_ci';

            $table->integer('id', autoIncrement: true);
            $table->string('nationality', 100);
            $table->string('c_pais', 10)->nullable();
            $table->string('country', 100)->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->integer('created_user_id')->nullable();
            $table->dateTime('updated_at')->useCurrentOnUpdate()->nullable();
            $table->integer('updated_user_id')->nullable();
            $table->dateTime('deleted_at')->nullable();
            $table->integer('deleted_user_id')->nullable();
            $table->primary(['id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nationalities');
    }
};
