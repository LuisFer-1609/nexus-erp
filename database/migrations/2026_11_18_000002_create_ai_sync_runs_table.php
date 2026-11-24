<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Historial de cargas del asistente. Alimenta la vista de estado.
     */
    public function up(): void
    {
        Schema::create('ai_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->string('status', 24);
            $table->string('trigger', 16);
            $table->integer('company_id')->nullable();
            $table->string('scope_table', 64)->nullable();
            $table->char('schema_fingerprint', 64)->nullable();
            $table->unsignedInteger('tables_indexed')->default(0);
            $table->unsignedInteger('rows_scanned')->default(0);
            $table->unsignedInteger('chunks_created')->default(0);
            $table->unsignedInteger('chunks_updated')->default(0);
            $table->unsignedInteger('chunks_unchanged')->default(0);
            $table->unsignedInteger('chunks_deleted')->default(0);
            $table->json('details')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'started_at'], 'ix_ai_sync_runs_status_started');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_sync_runs');
    }
};
