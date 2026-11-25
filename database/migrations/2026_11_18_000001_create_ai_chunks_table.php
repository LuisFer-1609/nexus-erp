<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Almacén vectorial de Nexus IA.
     *
     * Cada fila es un fragmento de texto de la plataforma con su embedding y la
     * empresa a la que pertenece. El vector se guarda empaquetado (float32) en
     * una columna binaria para funcionar igual en cualquier MySQL/MariaDB.
     */
    public function up(): void
    {
        Schema::create('ai_chunks', function (Blueprint $table) {
            $table->id();
            $table->integer('company_id');
            $table->string('source_table', 64);
            $table->string('source_id', 64);
            $table->string('kind', 16)->default('row');
            $table->unsignedSmallInteger('chunk_index')->default(0);
            $table->mediumText('content');
            $table->char('content_hash', 64);
            $table->binary('embedding');
            $table->string('embedding_model', 100);
            $table->unsignedSmallInteger('dimensions');
            $table->timestamps();

            $table->unique(
                ['company_id', 'source_table', 'source_id', 'kind', 'chunk_index'],
                'uq_ai_chunks_source',
            );
            $table->index(['company_id', 'source_table'], 'ix_ai_chunks_company_table');
            $table->foreign('company_id', 'fk_ai_chunks_company')
                ->references('id')->on('companies')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_chunks');
    }
};
