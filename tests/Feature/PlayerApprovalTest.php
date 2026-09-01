<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admission to the league, as distinct from admission to the website.
 *
 * Anyone may hold an account. Only an approved account may enter a tournament,
 * and the rule is enforced at every path that can create a registration --
 * self-service, the administrator override that shares the same controller
 * method, and the administrator registrant form.
 */
class PlayerApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_account_starts_pending(): void
    {
        // Created directly rather than through the factory. The factory
        // deliberately produces APPROVED users so the sixteen other test files
        // that create a user and then hit a gated route are unaffected, which
        // means the factory cannot also demonstrate the column's default.
        $user = User::create([
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'ada@example.com',
            'password' => 'irrelevant',
        ]);

        $this->assertSame('pending', $user->fresh()->approval_status);
        $this->assertFalse($user->isApproved());
        $this->assertTrue($user->isPendingApproval());
    }

    public function test_the_factory_produces_approved_users_by_default(): void
    {
        // Asserted rather than assumed: if this default ever flips, sixteen
        // unrelated test files break at once and the cause is not obvious from
        // any of their failures.
        $this->assertTrue(User::factory()->create()->isApproved());
    }

    public function test_the_factory_can_produce_pending_and_rejected_users(): void
    {
        $this->assertTrue(User::factory()->pending()->create()->isPendingApproval());

        $rejected = User::factory()->rejected()->create();

        $this->assertFalse($rejected->isApproved());
        $this->assertFalse($rejected->isPendingApproval());
    }

    public function test_scopes_select_the_right_accounts(): void
    {
        User::factory()->create();
        User::factory()->pending()->create();
        User::factory()->rejected()->create();

        $this->assertSame(1, User::approved()->count());
        $this->assertSame(1, User::awaitingApproval()->count());
    }
}
