<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tags a Result with how it was triggered:
     * - "manual": user-driven, one-off run (default treatment when null)
     * - "scheduled": part of a periodic run (cron, external scheduler, future internal scheduler)
     *
     * Distinct from the existing `source` column (api|manual|import|mcp) which
     * describes the *protocol* a Result arrived via — `run_source` describes the
     * *cadence*. A scheduled cron job can post via `source=api` and tag
     * `run_source=scheduled` so analytical pipelines can filter by cadence.
     */
    public function up(): void
    {
        Schema::table('results', function (Blueprint $table) {
            $table->string('run_source')->nullable()->after('source');
            $table->index('run_source');
        });
    }

    public function down(): void
    {
        Schema::table('results', function (Blueprint $table) {
            $table->dropIndex(['run_source']);
            $table->dropColumn('run_source');
        });
    }
};
