<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Laravel's database notification channel.
 *
 * Hand-written rather than taken from `php artisan notifications:table`,
 * because that stub declares morphs('notifiable') -- an unsigned bigint -- and
 * every user in this application has a ULID primary key. The stub would migrate
 * without complaint and then fail on the first notification anybody sent, which
 * is why TournamentPlacementNotificationTest sends a real one rather than
 * inspecting the schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->ulidMorphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
