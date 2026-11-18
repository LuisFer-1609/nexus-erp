<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_concepts', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_general_ci';

            $table->integer('id', autoIncrement: true);
            $table->integer('company_id');
            $table->integer('payroll_concept_type_id');
            $table->string('code', 50);
            $table->string('name', 150);
            $table->boolean('is_active')->default(1);
            $table->dateTime('created_at')->useCurrent();
            $table->integer('created_user_id')->nullable();
            $table->dateTime('updated_at')->useCurrentOnUpdate()->nullable();
            $table->integer('updated_user_id')->nullable();
            $table->dateTime('deleted_at')->nullable();
            $table->integer('deleted_user_id')->nullable();
            $table->index(['company_id'], 'fk_payroll_concepts_company');
            $table->index(['payroll_concept_type_id'], 'fk_payroll_concepts_type');
            $table->foreign('company_id', 'fk_payroll_concepts_company')->references('id')->on('companies');
            $table->foreign('payroll_concept_type_id', 'fk_payroll_concepts_type')->references('id')->on('payroll_concept_types');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_concepts');
    }
};
