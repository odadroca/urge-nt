<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A pipeline channel's input is now configurable:
     * - "prompt" (default): the rendered prompt content (existing behaviour)
     * - "result_history": a serialized batch of past Results matching input_filters,
     *   used for trend / drift analysis pipelines on top of periodic runs
     *
     * input_filters JSON shape (all keys optional except where noted):
     * {
     *   "prompt_slug": "daily-summary",  // defaults to the prompt being run
     *   "owner":       "alice",          // defaults to running user
     *   "since":       "P30D",           // ISO 8601 duration
     *   "limit":       50,               // capped at 100
     *   "run_source":  "scheduled",      // optional cadence filter
     *   "include_failures": false        // exclude status=error results
     * }
     */
    public function up(): void
    {
        Schema::table('pipeline_channels', function (Blueprint $table) {
            $table->string('input_source')->default('prompt')->after('system_prompt');
            $table->json('input_filters')->nullable()->after('input_source');
        });
    }

    public function down(): void
    {
        Schema::table('pipeline_channels', function (Blueprint $table) {
            $table->dropColumn(['input_source', 'input_filters']);
        });
    }
};
