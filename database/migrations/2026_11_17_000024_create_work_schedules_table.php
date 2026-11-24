<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_schedules', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->integer('id', autoIncrement: true);
            $table->integer('employee_id');
            $table->tinyInteger('weekday');
            $table->time('check_in_time')->nullable();
            $table->time('check_out_time')->nullable();
            $table->boolean('is_rest_day')->default(0);
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->integer('created_user_id')->nullable();
            $table->dateTime('updated_at')->useCurrentOnUpdate()->nullable();
            $table->integer('updated_user_id')->nullable();
            $table->dateTime('deleted_at')->nullable();
            $table->integer('deleted_user_id')->nullable();
            $table->primary(['id']);
            $table->unique(['employee_id', 'weekday', 'valid_from'], 'uq_work_schedules_emp_day_from');
            $table->foreign('employee_id', 'fk_work_schedules_employee')->references('id')->on('employees');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_schedules');
    }
};
