<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Give venue points a season of their own.
 *
 * They carried only an event_date, and the season they counted toward was
 * worked out by asking which season's date range contained that date. That is
 * not a stored fact, it is a coincidence of two other numbers -- so editing a
 * season's start or end date silently moved venue points between seasons, and
 * with them who qualified for the finale. Nothing errored; the figures simply
 * changed.
 *
 * The column is nullable on purpose. Backfilling can only reach rows whose date
 * actually falls inside a season, and a row recorded for a night outside every
 * season has no answer -- refusing to migrate over it would be worse than
 * admitting it. New rows always get one: the controller resolves the season
 * from the date and will not accept a date no season covers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venue_points', function (Blueprint $table) {
            $table->foreignUlid('season_id')
                ->nullable()
                ->after('venue_id')
                ->constrained('seasons')
                ->nullOnDelete();
        });

        // Backfill from the attribution that was in use until now, so nothing
        // moves the moment this runs: every row keeps the season it was already
        // being counted toward.
        //
        // Oldest season first, so that where ranges overlap -- which nothing
        // prevents -- a row lands in the earlier one deterministically rather
        // than depending on row order.
        foreach (DB::table('seasons')->orderBy('start_date')->get() as $season) {
            DB::table('venue_points')
                ->whereNull('season_id')
                ->whereBetween('event_date', [$season->start_date, $season->end_date])
                ->update(['season_id' => $season->id]);
        }
    }

    public function down(): void
    {
        Schema::table('venue_points', function (Blueprint $table) {
            $table->dropForeign(['season_id']);
            $table->dropColumn('season_id');
        });
    }
};
