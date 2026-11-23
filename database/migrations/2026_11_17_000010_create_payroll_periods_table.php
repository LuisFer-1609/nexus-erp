<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_general_ci';

            $table->integer('id', autoIncrement: true);
            $table->integer('company_id');
            $table->integer('payroll_frequency_id');
            $table->date('start_date');
            $table->date('end_date');
            $table->date('payment_date')->nullable();
            $table->string('status', 20);
            $table->dateTime('created_at')->useCurrent();
            $table->integer('created_user_id')->nullable();
            $table->dateTime('updated_at')->useCurrentOnUpdate()->nullable();
            $table->integer('updated_user_id')->nullable();
            $table->dateTime('deleted_at')->nullable();
            $table->integer('deleted_user_id')->nullable();
            $table->unique(['company_id', 'payroll_frequency_id', 'start_date'], 'uq_payroll_periods');
            $table->index(['payroll_frequency_id'], 'fk_payroll_periods_freq');
            $table->foreign('company_id', 'fk_payroll_periods_company')->references('id')->on('companies');
            $table->foreign('payroll_frequency_id', 'fk_payroll_periods_freq')->references('id')->on('payroll_frequencies');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_periods');
    }
};
