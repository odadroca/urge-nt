<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('llm_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('driver', 50); // openai, anthropic, mistral, gemini, ollama, openrouter
            $table->text('api_key')->nullable(); // encrypted
            $table->string('model')->nullable();
            $table->string('endpoint')->nullable();
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('llm_providers');
    }
};
