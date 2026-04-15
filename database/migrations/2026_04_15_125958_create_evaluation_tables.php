<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('result_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('result_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('evaluation_version')->default(1);
            $table->string('pipeline_run_id', 36)->nullable();
            $table->foreignId('evaluation_prompt_version_id')
                ->nullable()
                ->constrained('prompt_versions')
                ->nullOnDelete();
            $table->string('evaluator_provider');
            $table->string('evaluator_model');
            $table->string('dimension', 50);
            $table->tinyInteger('score');
            $table->text('reasoning')->nullable();
            $table->decimal('weight', 3, 2)->default(1.00);
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(
                ['result_id', 'evaluation_version', 'dimension'],
                'eval_version_dimension_unique'
            );
            $table->index(['result_id', 'evaluation_version']);
        });

        Schema::create('evaluation_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('result_evaluations');
        Schema::dropIfExists('evaluation_settings');
    }
};
