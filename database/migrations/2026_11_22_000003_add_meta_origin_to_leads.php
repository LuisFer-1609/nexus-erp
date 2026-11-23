<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->integer('responsible_user_id')->nullable()->change();
            $table->string('meta_lead_id', 64)->nullable();
            $table->string('meta_page_id', 64)->nullable();
            $table->string('meta_form_id', 64)->nullable();
            $table->string('meta_campaign_id', 64)->nullable();
            $table->string('meta_ad_id', 64)->nullable();
            $table->string('meta_platform', 30)->nullable();
            $table->json('meta_fields')->nullable();
            $table->unique(['company_id', 'meta_lead_id'], 'leads_company_meta_lead_unique');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropUnique('leads_company_meta_lead_unique');
            $table->dropColumn(['meta_lead_id', 'meta_page_id', 'meta_form_id', 'meta_campaign_id', 'meta_ad_id', 'meta_platform', 'meta_fields']);
        });

        // Keep the column nullable if imported leads have no assigned ERP user.
        if (! DB::table('leads')->whereNull('responsible_user_id')->exists()) {
            Schema::table('leads', function (Blueprint $table) {
                $table->integer('responsible_user_id')->nullable(false)->change();
            });
        }
    }
};
