<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oauth_refresh_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('token', 128)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('client_id', 2048);
            $table->string('scope')->default('mcp:read');
            $table->foreignId('access_token_id')->constrained('oauth_access_tokens')->cascadeOnDelete();
            $table->dateTime('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oauth_refresh_tokens');
    }
};
