<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PB-4 / LLM-05: Result.response_text is now `encrypted` cast in the
 * model. Equality lookups (used by ImportV1Command for dedup) become
 * impossible. Store a sha256 hash alongside the encrypted column so
 * dedup queries can use the hash.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('results', function (Blueprint $table) {
            $table->char('response_hash', 64)->nullable()->after('response_text');
            $table->index('response_hash');
        });
    }

    public function down(): void
    {
        Schema::table('results', function (Blueprint $table) {
            $table->dropIndex(['response_hash']);
            $table->dropColumn('response_hash');
        });
    }
};
