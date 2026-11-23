<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_general_ci';

            $table->integer('id', autoIncrement: true);
            $table->integer('company_id');
            $table->integer('lead_id')->nullable();
            $table->integer('lead_source_id')->nullable();
            $table->integer('business_type_id');
            $table->integer('responsible_user_id');
            $table->string('code', 50)->nullable();
            $table->string('legal_name', 255)->nullable();
            $table->string('business_name', 255)->nullable();
            $table->string('rfc', 13)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('status', 30)->default('activo');
            $table->dateTime('created_at')->useCurrent();
            $table->integer('created_user_id')->nullable();
            $table->dateTime('updated_at')->useCurrentOnUpdate()->nullable();
            $table->integer('updated_user_id')->nullable();
            $table->dateTime('deleted_at')->nullable();
            $table->integer('deleted_user_id')->nullable();
            $table->primary(['id']);
            $table->unique(['lead_id'], 'lead_id');
            $table->index(['company_id'], 'fk_customers_company');
            $table->index(['lead_source_id'], 'fk_customers_source');
            $table->index(['business_type_id'], 'fk_customers_btype');
            $table->index(['responsible_user_id'], 'fk_customers_responsible');
            $table->foreign('business_type_id', 'fk_customers_btype')->references('id')->on('business_types');
            $table->foreign('company_id', 'fk_customers_company')->references('id')->on('companies');
            $table->foreign('lead_id', 'fk_customers_lead')->references('id')->on('leads');
            $table->foreign('responsible_user_id', 'fk_customers_responsible')->references('id')->on('users');
            $table->foreign('lead_source_id', 'fk_customers_source')->references('id')->on('lead_sources');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
