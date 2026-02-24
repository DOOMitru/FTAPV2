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
        Schema::create('seasons', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_current')->default(false);
            $table->timestamps();
        });

        Schema::create('venues', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('address')->nullable();
            $table->timestamps();
        });

        Schema::create('tournaments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->dateTime('start_time');
            $table->foreignUlid('season_id')->constrained('seasons')->cascadeOnDelete();
            $table->foreignUlid('venue_id')->constrained('venues')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('points_structure', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->integer('place');
            $table->bigInteger('points');
            $table->timestamps();
        });

        Schema::create('tournament_registrants', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('player_name');
            $table->string('player_nickname')->nullable();
            $table->dateTime('registered_at');
            $table->foreignUlid('tournament_id')->constrained('tournaments')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('tournament_results', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->integer('place');
            $table->bigInteger('points');
            $table->foreignUlid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('player_name');
            $table->string('player_nickname')->nullable();
            $table->foreignUlid('tournament_id')->constrained('tournaments')->cascadeOnDelete();
            $table->unique(['tournament_id', 'user_id'], 'tr_tournament_user_unique');
            $table->timestamps();
        });

        Schema::create('venue_points', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->date('event_date');
            $table->bigInteger('amount');
            $table->foreignUlid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_name');
            $table->foreignUlid('venue_id')->constrained('venues')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('venue_points');
        Schema::dropIfExists('tournament_results');
        Schema::dropIfExists('tournament_registrants');
        Schema::dropIfExists('points_structure');
        Schema::dropIfExists('tournaments');
        Schema::dropIfExists('venues');
        Schema::dropIfExists('seasons');
    }
};
