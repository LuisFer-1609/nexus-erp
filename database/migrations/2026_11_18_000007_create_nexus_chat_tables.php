<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Historial del chat de Nexus: conversaciones por usuario, sus mensajes
     * (con la referencia al mensaje al que responden) y los archivos adjuntos.
     * Los archivos viven en el disco privado; aquí solo se guarda su ruta.
     */
    public function up(): void
    {
        Schema::create('nexus_chats', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->integer('company_id');
            $table->foreignId('user_id')->constrained('auth_users')->cascadeOnDelete();
            $table->string('title', 120);
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'last_message_at'], 'ix_nexus_chats_user_last');
            $table->foreign('company_id', 'fk_nexus_chats_company')
                ->references('id')->on('companies')->cascadeOnDelete();
        });

        Schema::create('nexus_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('conversation_id')->constrained('nexus_chats')->cascadeOnDelete();
            $table->string('role', 10);
            $table->text('body');
            $table->foreignId('reply_to_id')->nullable()->constrained('nexus_chat_messages')->nullOnDelete();
            // Reportes y acciones que acompañan a una respuesta de Nexus.
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'id'], 'ix_nexus_chat_msg_conversation');
        });

        Schema::create('nexus_chat_attachments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('message_id')->constrained('nexus_chat_messages')->cascadeOnDelete();
            $table->string('name', 255);
            $table->string('mime', 100);
            $table->unsignedInteger('size_bytes');
            $table->string('path', 255);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexus_chat_attachments');
        Schema::dropIfExists('nexus_chat_messages');
        Schema::dropIfExists('nexus_chats');
    }
};
