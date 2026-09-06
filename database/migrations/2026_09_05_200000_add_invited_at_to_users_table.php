<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Record when a player was sent an invitation.
 *
 * Real state about the account, not bookkeeping for a script: an administrator
 * looking at a player who has never signed in wants to know whether anyone ever
 * told them the account exists.
 *
 * It is also what makes `users:invite` resumable. The league's mail host caps
 * how much can be sent in an hour, so inviting two hundred players is several
 * runs -- and without a record of who has already been reached, the second run
 * either starts from the beginning or has to be tracked by hand.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('invited_at')->nullable()->after('approval_decided_by');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('invited_at');
        });
    }
};
