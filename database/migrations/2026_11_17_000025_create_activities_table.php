<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->integer('id', autoIncrement: true);
            $table->integer('lead_id')->nullable();
            $table->integer('customer_id')->nullable();
            $table->integer('user_id')->nullable();
            $table->string('activity_type', 30);
            $table->text('description')->nullable();
            $table->dateTime('occurred_at');
            $table->dateTime('created_at')->useCurrent();
            $table->integer('created_user_id')->nullable();
            $table->dateTime('updated_at')->useCurrentOnUpdate()->nullable();
            $table->integer('updated_user_id')->nullable();
            $table->dateTime('deleted_at')->nullable();
            $table->integer('deleted_user_id')->nullable();
            $table->index(['lead_id'], 'fk_activities_lead');
            $table->index(['customer_id'], 'fk_activities_customer');
            $table->index(['user_id'], 'fk_activities_user');
            $table->foreign('customer_id', 'fk_activities_customer')->references('id')->on('customers');
            $table->foreign('lead_id', 'fk_activities_lead')->references('id')->on('leads');
            $table->foreign('user_id', 'fk_activities_user')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
