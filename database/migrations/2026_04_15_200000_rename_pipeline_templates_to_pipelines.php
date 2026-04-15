<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('pipeline_templates', 'pipelines');
        Schema::rename('pipeline_template_channels', 'pipeline_channels');

        Schema::table('pipeline_channels', function (Blueprint $table) {
            $table->renameColumn('pipeline_template_id', 'pipeline_id');
        });

        Schema::table('results', function (Blueprint $table) {
            $table->renameColumn('pipeline_template_id', 'pipeline_id');
        });
    }

    public function down(): void
    {
        Schema::table('results', function (Blueprint $table) {
            $table->renameColumn('pipeline_id', 'pipeline_template_id');
        });

        Schema::table('pipeline_channels', function (Blueprint $table) {
            $table->renameColumn('pipeline_id', 'pipeline_template_id');
        });

        Schema::rename('pipeline_channels', 'pipeline_template_channels');
        Schema::rename('pipelines', 'pipeline_templates');
    }
};
