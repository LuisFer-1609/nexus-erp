<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_details', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_general_ci';

            $table->integer('id', autoIncrement: true);
            $table->integer('payroll_id');
            $table->integer('payroll_concept_id');
            $table->decimal('amount', 12, 2)->default('0.00');
            $table->boolean('is_manual_adjustment')->default(0);
            $table->string('notes', 255)->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->integer('created_user_id')->nullable();
            $table->dateTime('updated_at')->useCurrentOnUpdate()->nullable();
            $table->integer('updated_user_id')->nullable();
            $table->dateTime('deleted_at')->nullable();
            $table->integer('deleted_user_id')->nullable();
            $table->primary(['id']);
            $table->index(['payroll_id'], 'fk_payroll_details_payroll');
            $table->index(['payroll_concept_id'], 'fk_payroll_details_concept');
            $table->foreign('payroll_concept_id', 'fk_payroll_details_concept')->references('id')->on('payroll_concepts');
            $table->foreign('payroll_id', 'fk_payroll_details_payroll')->references('id')->on('payrolls');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_details');
    }
};
