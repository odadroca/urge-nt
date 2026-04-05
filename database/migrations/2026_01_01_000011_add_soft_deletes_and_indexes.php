<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Category soft deletes
        Schema::table('categories', function (Blueprint $table) {
            $table->softDeletes();
        });

        // Performance indexes
        Schema::table('prompts', function (Blueprint $table) {
            $table->index('type');
            $table->index('created_by');
        });

        Schema::table('results', function (Blueprint $table) {
            $table->index('created_by');
            $table->index('starred');
        });

        Schema::table('collections', function (Blueprint $table) {
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('prompts', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropIndex(['created_by']);
        });

        Schema::table('results', function (Blueprint $table) {
            $table->dropIndex(['created_by']);
            $table->dropIndex(['starred']);
        });

        Schema::table('collections', function (Blueprint $table) {
            $table->dropIndex(['created_by']);
        });
    }
};
