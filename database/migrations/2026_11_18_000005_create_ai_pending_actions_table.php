<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Acciones que Nexus propone y que modifican datos (por ejemplo, generar una
     * pre-nómina). Nunca se ejecutan solas: esperan la confirmación del usuario.
     */
    public function up(): void
    {
        Schema::create('ai_pending_actions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->integer('company_id');
            $table->foreignId('user_id')->nullable()->constrained('auth_users')->nullOnDelete();
            $table->string('type', 40);
            $table->string('title', 200);
            $table->json('payload');
            $table->string('status', 16)->default('pending');
            $table->json('result')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['company_id', 'status'], 'ix_ai_pending_company_status');
            $table->foreign('company_id', 'fk_ai_pending_company')
                ->references('id')->on('companies')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_pending_actions');
    }
};
