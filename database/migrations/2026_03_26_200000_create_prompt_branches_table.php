<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create prompt_branches table
        Schema::create('prompt_branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prompt_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->foreignId('head_version_id')->nullable()->constrained('prompt_versions')->nullOnDelete();
            $table->foreignId('forked_from_version_id')->nullable()->constrained('prompt_versions')->nullOnDelete();
            $table->boolean('is_default')->default(false);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->unique(['prompt_id', 'name']);
            $table->index(['prompt_id', 'is_default']);
        });

        // 2. Backfill: create a "main" branch for every existing prompt
        $prompts = DB::table('prompts')->get(['id', 'created_by']);
        $now = now()->toDateTimeString();

        foreach ($prompts as $prompt) {
            $latestVersion = DB::table('prompt_versions')
                ->where('prompt_id', $prompt->id)
                ->orderByDesc('version_number')
                ->first(['id']);

            DB::table('prompt_branches')->insert([
                'prompt_id' => $prompt->id,
                'name' => 'main',
                'head_version_id' => $latestVersion?->id,
                'forked_from_version_id' => null,
                'is_default' => true,
                'created_by' => $prompt->created_by,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // 3. Add default_branch_id to prompts
        Schema::table('prompts', function (Blueprint $table) {
            $table->foreignId('default_branch_id')
                ->nullable()
                ->after('pinned_version_id')
                ->constrained('prompt_branches')
                ->nullOnDelete();
        });

        // Set default_branch_id to the newly created main branch for each prompt
        foreach ($prompts as $prompt) {
            $mainBranch = DB::table('prompt_branches')
                ->where('prompt_id', $prompt->id)
                ->where('name', 'main')
                ->first(['id']);

            if ($mainBranch) {
                DB::table('prompts')
                    ->where('id', $prompt->id)
                    ->update(['default_branch_id' => $mainBranch->id]);
            }
        }

        // 4. Add branch_id and branch_version_number to prompt_versions
        Schema::table('prompt_versions', function (Blueprint $table) {
            $table->foreignId('branch_id')
                ->nullable()
                ->after('prompt_id')
                ->constrained('prompt_branches')
                ->nullOnDelete();
            $table->unsignedInteger('branch_version_number')
                ->nullable()
                ->after('version_number');

            $table->index(['branch_id', 'branch_version_number']);
        });

        // 5. Assign all existing versions to their prompt's "main" branch
        foreach ($prompts as $prompt) {
            $mainBranch = DB::table('prompt_branches')
                ->where('prompt_id', $prompt->id)
                ->where('name', 'main')
                ->first(['id']);

            if ($mainBranch) {
                // Set branch_id and branch_version_number = version_number for linear history
                DB::table('prompt_versions')
                    ->where('prompt_id', $prompt->id)
                    ->update(['branch_id' => $mainBranch->id]);

                $versions = DB::table('prompt_versions')
                    ->where('prompt_id', $prompt->id)
                    ->get(['id', 'version_number']);

                foreach ($versions as $version) {
                    DB::table('prompt_versions')
                        ->where('id', $version->id)
                        ->update(['branch_version_number' => $version->version_number]);
                }
            }
        }
    }

    public function down(): void
    {
        // Reverse step 4: remove branch columns from prompt_versions
        Schema::table('prompt_versions', function (Blueprint $table) {
            $table->dropIndex(['branch_id', 'branch_version_number']);
            $table->dropConstrainedForeignId('branch_id');
            $table->dropColumn('branch_version_number');
        });

        // Reverse step 3: remove default_branch_id from prompts
        Schema::table('prompts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_branch_id');
        });

        // Reverse steps 1 & 2: drop prompt_branches table
        Schema::dropIfExists('prompt_branches');
    }
};
