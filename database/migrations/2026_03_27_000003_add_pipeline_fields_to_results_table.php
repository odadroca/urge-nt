<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('results', function (Blueprint $table) {
            $table->string('role_label')->nullable()->after('source');
            $table->foreignId('pipeline_template_id')
                ->nullable()->constrained()->nullOnDelete()
                ->after('role_label');
        });
    }

    public function down(): void
    {
        Schema::table('results', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pipeline_template_id');
            $table->dropColumn('role_label');
        });
    }
};
