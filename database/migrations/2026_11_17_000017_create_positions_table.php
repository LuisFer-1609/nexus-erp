<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('positions', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_general_ci';

            $table->integer('id', autoIncrement: true);
            $table->integer('company_id');
            $table->integer('area_id')->nullable();
            $table->string('code', 50);
            $table->string('name', 150);
            $table->dateTime('created_at')->useCurrent();
            $table->integer('created_user_id')->nullable();
            $table->dateTime('updated_at')->useCurrentOnUpdate()->nullable();
            $table->integer('updated_user_id')->nullable();
            $table->dateTime('deleted_at')->nullable();
            $table->integer('deleted_user_id')->nullable();
            $table->index(['company_id'], 'fk_positions_company');
            $table->index(['area_id'], 'fk_positions_area');
            $table->foreign('area_id', 'fk_positions_area')->references('id')->on('areas');
            $table->foreign('company_id', 'fk_positions_company')->references('id')->on('companies');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('positions');
    }
};
