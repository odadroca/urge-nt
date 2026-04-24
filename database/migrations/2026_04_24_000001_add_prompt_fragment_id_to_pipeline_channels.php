<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pipeline_channels', function (Blueprint $table) {
            $table->foreignId('prompt_fragment_id')
                ->nullable()
                ->after('system_prompt')
                ->constrained('prompts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pipeline_channels', function (Blueprint $table) {
            $table->dropForeign(['prompt_fragment_id']);
            $table->dropColumn('prompt_fragment_id');
        });
    }
};
