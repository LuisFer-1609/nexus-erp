<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meta_connections', fn (Blueprint $table) => $table->index('company_id'));
        Schema::table('meta_connections', function (Blueprint $table) {
            $table->dropUnique('meta_connections_company_id_unique');
            $table->foreignId('auth_user_id')->nullable()->after('company_id')->constrained('auth_users')->nullOnDelete();
            $table->string('meta_user_id', 64)->nullable();
            $table->string('business_id', 64)->nullable();
            $table->string('business_name')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unique('auth_user_id');
            $table->index(['company_id', 'business_id']);
        });

        foreach (['meta_ad_accounts', 'meta_pages'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('meta_connection_id')->nullable()->constrained('meta_connections')->nullOnDelete();
            });
        }
        Schema::table('meta_ad_accounts', fn (Blueprint $table) => $table->string('currency', 10)->nullable());
    }

    public function down(): void
    {
        Schema::table('meta_ad_accounts', fn (Blueprint $table) => $table->dropColumn('currency'));
        foreach (['meta_ad_accounts', 'meta_pages'] as $tableName) {
            Schema::table($tableName, fn (Blueprint $table) => $table->dropConstrainedForeignId('meta_connection_id'));
        }

        Schema::table('meta_connections', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'business_id']);
            $table->dropUnique(['auth_user_id']);
            $table->dropConstrainedForeignId('auth_user_id');
            $table->dropColumn(['meta_user_id', 'business_id', 'business_name', 'expires_at']);
            $table->unique('company_id');
        });
        Schema::table('meta_connections', fn (Blueprint $table) => $table->dropIndex(['company_id']));
    }
};
