<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('key_hash')->unique();
            $table->string('key_preview', 8);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('key_hash');
        });

        Schema::create('api_key_prompt', function (Blueprint $table) {
            $table->foreignId('api_key_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prompt_id')->constrained()->cascadeOnDelete();
            $table->primary(['api_key_id', 'prompt_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_key_prompt');
        Schema::dropIfExists('api_keys');
    }
};
