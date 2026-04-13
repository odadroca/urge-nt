<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oauth_authorization_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 128)->unique();
            $table->string('client_id', 2048);
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('redirect_uri', 2048);
            $table->string('scope')->default('mcp:read');
            $table->string('code_challenge', 128);
            $table->string('code_challenge_method', 10)->default('S256');
            $table->string('resource', 2048)->nullable();
            $table->dateTime('expires_at');
            $table->timestamps();
        });

        Schema::create('oauth_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('token', 128)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('client_id', 2048);
            $table->string('scope')->default('mcp:read');
            $table->dateTime('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oauth_access_tokens');
        Schema::dropIfExists('oauth_authorization_codes');
    }
};
