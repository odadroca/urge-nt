<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prompts', function (Blueprint $table) {
            $table->string('visibility', 20)->default('private')->after('tags');
        });

        // Existing prompts become 'shared' to preserve current behavior
        DB::table('prompts')->update(['visibility' => 'shared']);
    }

    public function down(): void
    {
        Schema::table('prompts', function (Blueprint $table) {
            $table->dropColumn('visibility');
        });
    }
};
