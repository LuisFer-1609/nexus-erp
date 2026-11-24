<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_general_ci';

            $table->integer('id', autoIncrement: true);
            $table->integer('company_id');
            $table->integer('lead_source_id');
            $table->string('code', 50);
            $table->string('name', 150);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(1);
            $table->dateTime('created_at')->useCurrent();
            $table->integer('created_user_id')->nullable();
            $table->dateTime('updated_at')->useCurrentOnUpdate()->nullable();
            $table->integer('updated_user_id')->nullable();
            $table->dateTime('deleted_at')->nullable();
            $table->integer('deleted_user_id')->nullable();
            $table->primary(['id']);
            $table->index(['company_id'], 'fk_campaigns_company');
            $table->index(['lead_source_id'], 'fk_campaigns_source');
            $table->foreign('company_id', 'fk_campaigns_company')->references('id')->on('companies');
            $table->foreign('lead_source_id', 'fk_campaigns_source')->references('id')->on('lead_sources');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
