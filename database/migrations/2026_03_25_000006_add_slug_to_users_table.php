<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('name');
        });

        // Backfill slugs for existing users
        foreach (User::all() as $user) {
            $base = Str::slug($user->name);
            $slug = $base;
            $counter = 1;
            while (User::where('slug', $slug)->where('id', '!=', $user->id)->exists()) {
                $slug = $base.'-'.$counter++;
            }
            $user->update(['slug' => $slug]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->unique('slug');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
