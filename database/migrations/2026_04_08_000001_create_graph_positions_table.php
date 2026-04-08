<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('graph_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('node_type', 20);
            $table->unsignedBigInteger('node_id');
            $table->float('x');
            $table->float('y');
            $table->timestamps();

            $table->unique(['user_id', 'node_type', 'node_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('graph_positions');
    }
};
