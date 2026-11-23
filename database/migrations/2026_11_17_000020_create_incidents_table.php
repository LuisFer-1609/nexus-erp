<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_general_ci';

            $table->integer('id', autoIncrement: true);
            $table->integer('employee_id');
            $table->integer('incident_type_id');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('notes', 255)->nullable();
            $table->string('status', 30);
            $table->dateTime('created_at')->useCurrent();
            $table->integer('created_user_id')->nullable();
            $table->dateTime('updated_at')->useCurrentOnUpdate()->nullable();
            $table->integer('updated_user_id')->nullable();
            $table->dateTime('deleted_at')->nullable();
            $table->integer('deleted_user_id')->nullable();
            $table->index(['employee_id'], 'fk_incidents_employee');
            $table->index(['incident_type_id'], 'fk_incidents_type');
            $table->foreign('employee_id', 'fk_incidents_employee')->references('id')->on('employees');
            $table->foreign('incident_type_id', 'fk_incidents_type')->references('id')->on('incident_types');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};
