<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The public disk is only reachable over HTTP through public/storage, and its
 * absence is INVISIBLE: nothing errors, an uploaded file simply 404s and the
 * page shows a broken image.
 *
 * This is not new. ProfileController has written avatars to that disk since
 * before sponsors existed, and the gap went unnoticed only because no user has
 * ever uploaded one. Sponsor logos hit it on the first upload.
 */
class StorageLinkTest extends TestCase
{
    public function test_the_public_storage_link_exists(): void
    {
        $this->assertTrue(
            file_exists(public_path('storage')),
            'public/storage is missing. Run `php artisan storage:link` — without it every uploaded '
            .'file 404s, and the only symptom is a broken image.'
        );
    }

    public function test_the_link_resolves_to_the_public_disk(): void
    {
        // A stale link pointing somewhere else fails exactly like a missing
        // one, and looks perfectly fine in a directory listing.
        $this->assertSame(
            realpath(storage_path('app/public')),
            realpath(public_path('storage')),
            'public/storage does not resolve to storage/app/public.'
        );
    }
}
