<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nexus_conversations', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_general_ci';

            $table->integer('id', autoIncrement: true);
            $table->integer('user_id');
            $table->string('title', 255)->nullable();
            $table->dateTime('last_message_at')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrentOnUpdate()->nullable();
            $table->integer('created_user_id')->nullable();
            $table->integer('updated_user_id')->nullable();
            $table->dateTime('deleted_at')->nullable();
            $table->integer('deleted_user_id')->nullable();
            $table->primary(['id']);
            $table->index(['user_id'], 'fk_nexus_conv_user');
            $table->foreign('user_id', 'fk_nexus_conv_user')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexus_conversations');
    }
};
