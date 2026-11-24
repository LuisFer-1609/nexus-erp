<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_general_ci';

            $table->integer('id', autoIncrement: true);
            $table->string('name', 255);
            $table->string('business_name', 255)->nullable();
            $table->string('rfc', 13)->nullable();
            $table->string('type_of_business', 255)->nullable();
            $table->string('mail', 255)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('address', 255)->nullable();
            $table->boolean('is_active')->default(1);
            $table->primary(['id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
