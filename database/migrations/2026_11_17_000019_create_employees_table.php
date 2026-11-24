<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_general_ci';

            $table->integer('id', autoIncrement: true);
            $table->integer('id_user');
            $table->integer('nationality_id');
            $table->integer('payroll_frequency_id');
            $table->integer('area_id');
            $table->integer('position_id');
            $table->string('code', 50)->nullable();
            $table->string('sex', 20)->nullable();
            $table->string('curp', 18)->nullable();
            $table->string('ssn', 11)->nullable();
            $table->date('hire_date')->nullable();
            $table->string('status', 20)->default('activo');
            $table->dateTime('created_at')->useCurrent();
            $table->integer('created_user_id')->nullable();
            $table->dateTime('updated_at')->useCurrentOnUpdate()->nullable();
            $table->integer('updated_user_id')->nullable();
            $table->dateTime('deleted_at')->nullable();
            $table->integer('deleted_user_id')->nullable();
            $table->primary(['id']);
            $table->unique(['id_user'], 'id_user');
            $table->index(['nationality_id'], 'fk_employees_nat');
            $table->index(['payroll_frequency_id'], 'fk_employees_freq');
            $table->index(['area_id'], 'fk_employees_area');
            $table->index(['position_id'], 'fk_employees_position');
            $table->foreign('area_id', 'fk_employees_area')->references('id')->on('areas');
            $table->foreign('payroll_frequency_id', 'fk_employees_freq')->references('id')->on('payroll_frequencies');
            $table->foreign('nationality_id', 'fk_employees_nat')->references('id')->on('nationalities');
            $table->foreign('position_id', 'fk_employees_position')->references('id')->on('positions');
            $table->foreign('id_user', 'fk_employees_user')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
