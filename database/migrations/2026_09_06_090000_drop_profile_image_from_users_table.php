<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Profile pictures are gone. A player is drawn as their initials.
 *
 * Nobody ever uploaded one. The 205 accounts came from a CSV import that sets
 * no image, and the feature's real effect was to put the same stock face beside
 * every name until that was replaced by a monogram. What remained was an upload
 * control on two forms, a column, an accessor, and file handling in two
 * controllers -- all of it maintained for a picture nobody had.
 *
 * The stored value was a path into storage/app/public/profile-images. Dropping
 * the column does NOT delete those files: a migration that reaches into the
 * filesystem cannot be rolled back, and there is nothing to delete in
 * production anyway. If a development machine has any, they can go by hand.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('profile_image');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('profile_image')->nullable()->after('is_admin');
        });
    }
};
