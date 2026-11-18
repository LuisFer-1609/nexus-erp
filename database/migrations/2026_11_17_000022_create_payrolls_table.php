<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payrolls', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_general_ci';

            $table->integer('id', autoIncrement: true);
            $table->integer('payroll_period_id');
            $table->integer('employee_id');
            $table->integer('days_worked')->default(0);
            $table->integer('late_count')->default(0);
            $table->integer('absence_count')->default(0);
            $table->decimal('overtime_hours', 5, 2)->default('0.00');
            $table->decimal('total_perceptions', 12, 2)->default('0.00');
            $table->decimal('total_deductions', 12, 2)->default('0.00');
            $table->decimal('net_amount', 12, 2)->default('0.00');
            $table->string('status', 20);
            $table->dateTime('created_at')->useCurrent();
            $table->integer('created_user_id')->nullable();
            $table->dateTime('updated_at')->useCurrentOnUpdate()->nullable();
            $table->integer('updated_user_id')->nullable();
            $table->dateTime('deleted_at')->nullable();
            $table->integer('deleted_user_id')->nullable();
            $table->primary(['id']);
            $table->unique(['payroll_period_id', 'employee_id'], 'uq_payrolls_period_employee');
            $table->index(['employee_id'], 'fk_payrolls_employee');
            $table->foreign('employee_id', 'fk_payrolls_employee')->references('id')->on('employees');
            $table->foreign('payroll_period_id', 'fk_payrolls_period')->references('id')->on('payroll_periods');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payrolls');
    }
};
