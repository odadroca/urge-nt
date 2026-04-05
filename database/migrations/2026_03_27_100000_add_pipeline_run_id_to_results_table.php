<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('results', function (Blueprint $table) {
            $table->string('pipeline_run_id', 36)->nullable()->index()->after('pipeline_template_id');
        });

        // Backfill existing pipeline results: group by template + 60-second window
        $results = DB::table('results')
            ->whereNotNull('pipeline_template_id')
            ->orderBy('pipeline_template_id')
            ->orderBy('created_at')
            ->get();

        $currentTemplateId = null;
        $currentRunId = null;
        $lastCreatedAt = null;

        foreach ($results as $result) {
            if ($result->pipeline_template_id !== $currentTemplateId
                || $lastCreatedAt === null
                || abs(strtotime($result->created_at) - strtotime($lastCreatedAt)) > 60) {
                $currentRunId = (string) Str::uuid();
                $currentTemplateId = $result->pipeline_template_id;
            }
            $lastCreatedAt = $result->created_at;
            DB::table('results')->where('id', $result->id)->update(['pipeline_run_id' => $currentRunId]);
        }
    }

    public function down(): void
    {
        Schema::table('results', function (Blueprint $table) {
            $table->dropIndex(['pipeline_run_id']);
            $table->dropColumn('pipeline_run_id');
        });
    }
};
