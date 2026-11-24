<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * WhatsApp vinculado de cada usuario (por QR). Solo guarda el estado del
     * vínculo; las credenciales de la sesión viven en el puente, fuera de la base.
     */
    public function up(): void
    {
        Schema::create('whatsapp_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('auth_users')->cascadeOnDelete();
            $table->string('status', 16)->default('disconnected');
            $table->string('phone', 32)->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_links');
    }
};
