<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What it takes to reach the season finale.
 *
 * Three targets a player must all meet: points accumulated over the season,
 * tournaments won, and venue points collected. They live on the season because
 * they are a property of that season's rules -- a later season may set
 * different ones without rewriting the past.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seasons', function (Blueprint $table) {
            // Nullable, and null means NOT PUBLISHED rather than zero.
            //
            // Every season that existed before this feature has none, and the
            // public home page already tells visitors the numbers are still
            // being decided. A defaulted 0 would turn that admission into a
            // target nobody chose and that everybody has already cleared.
            $table->unsignedInteger('finale_points_required')->nullable()->after('is_current');
            $table->unsignedInteger('finale_wins_required')->nullable()->after('finale_points_required');
            $table->unsignedInteger('finale_venue_points_required')->nullable()->after('finale_wins_required');
        });
    }

    public function down(): void
    {
        Schema::table('seasons', function (Blueprint $table) {
            $table->dropColumn([
                'finale_points_required',
                'finale_wins_required',
                'finale_venue_points_required',
            ]);
        });
    }
};
