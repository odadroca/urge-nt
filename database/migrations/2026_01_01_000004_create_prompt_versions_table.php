<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prompt_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prompt_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->longText('content');
            $table->string('commit_message', 500)->nullable();
            $table->json('variables')->nullable();
            $table->json('variable_metadata')->nullable();
            $table->json('includes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('created_at')->nullable();

            $table->unique(['prompt_id', 'version_number']);
        });

        // Add FK for pinned_version_id now that prompt_versions exists
        Schema::table('prompts', function (Blueprint $table) {
            $table->foreign('pinned_version_id')
                ->references('id')
                ->on('prompt_versions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('prompts', function (Blueprint $table) {
            $table->dropForeign(['pinned_version_id']);
        });

        Schema::dropIfExists('prompt_versions');
    }
};
