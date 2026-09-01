<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Admission to the league, as distinct from admission to the website.
 *
 * Registration was open and unconditional: an account created through
 * /register could immediately enter any tournament whose window was open. For
 * a free-to-enter league running at partner venues with finite seats, that is
 * a door with no lock. This adds the point at which a person is admitted.
 *
 * A single status column rather than the two nullable timestamps that would
 * mirror email_verified_at. Three states expressed with two nullable columns
 * admit a fourth combination -- both set -- that means nothing, and nothing
 * would stop it arising. A status column cannot express a state that does not
 * exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Indexed because the pending queue reads it on every visit to the
            // user-management page, and the registrant pickers filter on it.
            $table->string('approval_status')->default('pending')->index()->after('is_admin');
            $table->timestamp('approval_decided_at')->nullable()->after('approval_status');
            // string, not foreignId: users.id is a ULID stored as varchar, so a
            // bigint foreign key would not match the column it references.
            $table->string('approval_decided_by')->nullable()->after('approval_decided_at');
        });

        // Grandfather every account that predates the requirement. They
        // registered when no approval step existed and cannot be expected to
        // have satisfied one -- the same reasoning, and the same shape, as the
        // email-verification backfill that preceded this.
        //
        // approval_decided_by stays null deliberately. No person made this
        // decision, and naming one would be a false audit trail on the exact
        // column that exists to make the trail trustworthy.
        DB::table('users')->update([
            'approval_status' => 'approved',
            'approval_decided_at' => DB::raw('created_at'),
        ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['approval_status', 'approval_decided_at', 'approval_decided_by']);
        });
    }
};
