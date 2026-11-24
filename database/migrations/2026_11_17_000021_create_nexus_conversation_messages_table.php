<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nexus_conversation_messages', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_general_ci';

            $table->integer('id', autoIncrement: true);
            $table->integer('conversation_id');
            $table->string('role', 20);
            $table->text('content');
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrentOnUpdate()->nullable();
            $table->primary(['id']);
            $table->index(['conversation_id'], 'fk_nexus_msg_conv');
            $table->foreign('conversation_id', 'fk_nexus_msg_conv')->references('id')->on('nexus_conversations');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexus_conversation_messages');
    }
};
