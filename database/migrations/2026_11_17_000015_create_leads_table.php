<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_general_ci';

            $table->integer('id', autoIncrement: true);
            $table->integer('company_id');
            $table->integer('lead_source_id');
            $table->integer('campaign_id')->nullable();
            $table->integer('lead_stage_id');
            $table->integer('responsible_user_id');
            $table->string('code', 50)->nullable();
            $table->string('full_name', 255);
            $table->string('email', 255)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('priority', 20)->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->integer('created_user_id')->nullable();
            $table->dateTime('updated_at')->useCurrentOnUpdate()->nullable();
            $table->integer('updated_user_id')->nullable();
            $table->dateTime('deleted_at')->nullable();
            $table->integer('deleted_user_id')->nullable();
            $table->primary(['id']);
            $table->index(['company_id'], 'fk_leads_company');
            $table->index(['lead_source_id'], 'fk_leads_source');
            $table->index(['campaign_id'], 'fk_leads_campaign');
            $table->index(['lead_stage_id'], 'fk_leads_stage');
            $table->index(['responsible_user_id'], 'fk_leads_responsible');
            $table->foreign('campaign_id', 'fk_leads_campaign')->references('id')->on('campaigns');
            $table->foreign('company_id', 'fk_leads_company')->references('id')->on('companies');
            $table->foreign('responsible_user_id', 'fk_leads_responsible')->references('id')->on('users');
            $table->foreign('lead_source_id', 'fk_leads_source')->references('id')->on('lead_sources');
            $table->foreign('lead_stage_id', 'fk_leads_stage')->references('id')->on('lead_stages');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
