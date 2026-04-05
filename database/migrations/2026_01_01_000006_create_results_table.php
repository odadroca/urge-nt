<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prompt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prompt_version_id')->constrained()->cascadeOnDelete();
            $table->string('source', 20); // 'api' | 'manual' | 'import'
            $table->string('provider_name', 100)->nullable();
            $table->string('model_name')->nullable();
            $table->foreignId('llm_provider_id')->nullable()->constrained()->nullOnDelete();
            $table->longText('rendered_content')->nullable();
            $table->json('variables_used')->nullable();
            $table->longText('response_text')->nullable();
            $table->text('notes')->nullable();
            $table->tinyInteger('rating')->nullable();
            $table->boolean('starred')->default(false);
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('status', 20)->default('success');
            $table->text('error_message')->nullable();
            $table->string('import_filename')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['prompt_version_id', 'starred']);
            $table->index(['prompt_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('results');
    }
};
