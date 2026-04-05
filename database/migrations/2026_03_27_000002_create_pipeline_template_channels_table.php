<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pipeline_template_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pipeline_template_id')
                  ->constrained()->cascadeOnDelete();
            $table->string('role_label');
            $table->foreignId('llm_provider_id')
                  ->nullable()->constrained()->nullOnDelete();
            $table->text('system_prompt')->nullable();
            $table->string('trigger', 20)->default('parallel');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pipeline_template_channels');
    }
};
