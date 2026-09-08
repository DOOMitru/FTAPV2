<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When an administrator declared a tournament's results final.
 *
 * Publishing does two things at once: it tells the players who scored, and it
 * locks the tournament. The second is what makes the first safe -- a place is a
 * position in a field, and without the lock a late registration would shift
 * every finish after the messages had already gone out.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->timestamp('published_at')->nullable()->after('start_time');
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn('published_at');
        });
    }
};
