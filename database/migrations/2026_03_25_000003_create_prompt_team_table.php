<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prompt_team', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prompt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['prompt_id', 'team_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prompt_team');
    }
};
