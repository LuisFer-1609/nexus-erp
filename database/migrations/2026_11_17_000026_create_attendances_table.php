<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_general_ci';

            $table->integer('id', autoIncrement: true);
            $table->integer('employee_id');
            $table->date('attendance_date');
            $table->time('expected_check_in')->nullable();
            $table->time('expected_check_out')->nullable();
            $table->time('check_in')->nullable();
            $table->time('check_out')->nullable();
            $table->integer('late_minutes')->default(0);
            $table->decimal('overtime_hours', 5, 2)->default('0.00');
            $table->string('status', 20);
            $table->dateTime('created_at')->useCurrent();
            $table->integer('created_user_id')->nullable();
            $table->dateTime('updated_at')->useCurrentOnUpdate()->nullable();
            $table->integer('updated_user_id')->nullable();
            $table->dateTime('deleted_at')->nullable();
            $table->integer('deleted_user_id')->nullable();
            $table->primary(['id']);
            $table->unique(['employee_id', 'attendance_date'], 'uq_attendances_emp_date');
            $table->foreign('employee_id', 'fk_attendances_employee')->references('id')->on('employees');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
