<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oauth_clients', function (Blueprint $table) {
            $table->id();
            $table->string('client_id', 128)->unique();
            $table->string('client_name', 255)->nullable();
            $table->json('redirect_uris');
            $table->json('grant_types')->default('["authorization_code"]');
            $table->json('response_types')->default('["code"]');
            $table->string('token_endpoint_auth_method', 50)->default('none');
            $table->string('scope', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oauth_clients');
    }
};
