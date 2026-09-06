<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The registration deadline is gone.
 *
 * It gated three things -- whether a player could enter, whether they could
 * withdraw, and whether an administrator could start knocking people out -- and
 * the league does not work that way: people turn up and play. Entry and
 * withdrawal now hang on whether the tournament has results, which is the
 * question that actually matters, because a place is a position in a field and
 * a recorded finish describes a field of a particular size.
 *
 * Dropped rather than left in place unused. A column nobody writes and nobody
 * reads is a question for whoever finds it next, and this one is worse than
 * most: its name suggests it still governs something.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn('scheduled_at');
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            // Nullable on the way back, unlike the original. Every row that
            // existed when this ran has lost its value, and a NOT NULL column
            // cannot be added to a table that already has rows without
            // inventing a deadline for each of them.
            $table->timestamp('scheduled_at')->nullable()->after('description');
        });
    }
};
