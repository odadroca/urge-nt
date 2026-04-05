<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collection_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_id')->constrained()->cascadeOnDelete();
            $table->string('item_type'); // 'prompt_version' or 'result'
            $table->unsignedBigInteger('item_id');
            $table->integer('sort_order')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['collection_id', 'item_type', 'item_id']);
            $table->index(['item_type', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_items');
    }
};
