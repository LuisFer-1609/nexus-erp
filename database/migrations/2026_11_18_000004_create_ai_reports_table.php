<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reportes PDF generados por Nexus. El archivo vive en el disco privado;
     * esta tabla permite descargarlo después, solo desde la empresa que lo creó.
     */
    public function up(): void
    {
        Schema::create('ai_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->integer('company_id');
            $table->foreignId('user_id')->nullable()->constrained('auth_users')->nullOnDelete();
            $table->string('title', 200);
            $table->string('path', 255);
            $table->unsignedInteger('size_bytes');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'created_at'], 'ix_ai_reports_company_created');
            $table->foreign('company_id', 'fk_ai_reports_company')
                ->references('id')->on('companies')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_reports');
    }
};
