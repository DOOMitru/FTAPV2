<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sponsors, managed rather than hardcoded.
 *
 * The wall on the home page was a PHP array of five invented businesses with
 * emoji standing in for logos, so adding a real sponsor meant editing a Blade
 * template and deploying. The about page sells "your logo goes on this site"
 * as a deliverable, which the site could not honour without a code change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sponsors', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            // Relative path on the public disk. Required: a wall of logos with
            // one text card among them looks broken, and consistency is the
            // whole point of a sponsor wall.
            $table->string('logo_path');
            $table->string('website_url')->nullable();
            // A string rather than an is_premium boolean. The brief names two
            // tiers, but a third -- "Founding", "Venue partner" -- is the kind
            // of thing a league adds, and a boolean cannot grow into one.
            $table->string('tier')->default('regular')->index();
            // Ordering WITHIN a tier. Without it the wall is ordered by
            // insertion, so moving a sponsor means deleting and re-adding it --
            // and since the logo is required, re-uploading the artwork.
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sponsors');
    }
};
