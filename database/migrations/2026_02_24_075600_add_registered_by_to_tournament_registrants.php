<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tournament_registrants', function (Blueprint $table) {
            $table->foreignUlid('registered_by')->nullable()->after('tournament_id')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tournament_registrants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('registered_by');
        });
    }
};
