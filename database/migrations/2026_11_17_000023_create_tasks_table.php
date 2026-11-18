<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->integer('id', autoIncrement: true);
            $table->integer('lead_id')->nullable();
            $table->integer('customer_id')->nullable();
            $table->integer('assigned_user_id');
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->dateTime('due_at');
            $table->string('status', 20)->default('pendiente');
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->integer('created_user_id')->nullable();
            $table->dateTime('updated_at')->useCurrentOnUpdate()->nullable();
            $table->integer('updated_user_id')->nullable();
            $table->dateTime('deleted_at')->nullable();
            $table->integer('deleted_user_id')->nullable();
            $table->index(['lead_id'], 'fk_tasks_lead');
            $table->index(['customer_id'], 'fk_tasks_customer');
            $table->index(['assigned_user_id'], 'fk_tasks_assigned');
            $table->foreign('assigned_user_id', 'fk_tasks_assigned')->references('id')->on('users');
            $table->foreign('customer_id', 'fk_tasks_customer')->references('id')->on('customers');
            $table->foreign('lead_id', 'fk_tasks_lead')->references('id')->on('leads');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
