<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_connections', function (Blueprint $table) {
            $table->id();
            $table->integer('company_id')->unique();
            $table->text('access_token');
            $table->string('token_fingerprint', 64)->unique();
            $table->timestamps();
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
        });

        Schema::create('meta_ad_accounts', function (Blueprint $table) {
            $table->id();
            $table->integer('company_id');
            $table->string('external_id', 64)->unique();
            $table->string('name');
            $table->timestamps();
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
        });

        Schema::create('meta_pages', function (Blueprint $table) {
            $table->id();
            $table->integer('company_id');
            $table->string('external_id', 64)->unique();
            $table->string('name');
            $table->timestamps();
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
        });

        Schema::create('meta_saved_campaigns', function (Blueprint $table) {
            $table->id();
            $table->integer('company_id');
            $table->string('ad_account_id', 64);
            $table->string('meta_campaign_id', 64);
            $table->string('name');
            $table->string('status', 40)->nullable();
            $table->string('effective_status', 40)->nullable();
            $table->string('objective', 100)->nullable();
            $table->string('currency', 10);
            $table->date('period_start');
            $table->date('period_end');
            $table->json('metrics');
            $table->timestamp('fetched_at');
            $table->foreignId('saved_by_auth_user_id')->constrained('auth_users');
            $table->timestamps();
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->unique(['company_id', 'meta_campaign_id', 'period_start', 'period_end'], 'meta_campaign_period_unique');
            $table->index(['company_id', 'ad_account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_saved_campaigns');
        Schema::dropIfExists('meta_pages');
        Schema::dropIfExists('meta_ad_accounts');
        Schema::dropIfExists('meta_connections');
    }
};
