<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Grandfather every account that existed before email verification was enforced.
 *
 * The `verified` middleware has been on the dashboard route since Phase 0, but
 * it was inert: Laravel only acts on it when the model implements
 * MustVerifyEmail, and User did not. Turning that on is a one-line change that
 * would retroactively gate people who registered when no verification step
 * existed -- including, on this database, an administrator.
 *
 * Nobody who signed up before the rule can be expected to have satisfied it, so
 * they are marked verified as of the moment they registered. From here the
 * requirement applies to new registrations only, which is the point of it.
 */
return new class extends Migration
{
    public function up(): void
    {
        // created_at rather than now(): the claim being recorded is "this
        // account predates the requirement", and its own creation time is the
        // honest timestamp for that. Stamping now() would assert that everyone
        // verified their address today, which nobody did.
        DB::table('users')
            ->whereNull('email_verified_at')
            ->update(['email_verified_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        // Deliberately empty.
        //
        // Reversing this would mean un-verifying accounts, and nothing here
        // records which rows were changed -- an account verified through the
        // real flow is indistinguishable from one grandfathered above. A
        // rollback would therefore lock out legitimately verified users,
        // including whoever ran it. Doing nothing is the safe reverse.
    }
};
