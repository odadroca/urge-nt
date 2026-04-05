<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prompts', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->unique(['created_by', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::table('prompts', function (Blueprint $table) {
            $table->dropUnique(['created_by', 'slug']);
            $table->unique('slug');
        });
    }
};
