<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collection_share_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_id')->constrained()->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->string('label')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_accessed_at')->nullable();
            $table->unsignedInteger('access_count')->default(0);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index('collection_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_share_links');
    }
};
