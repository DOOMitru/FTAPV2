<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Seeding the users table from a CSV.
 *
 * This is run once, against real people, by someone who cannot easily inspect
 * what it did. So the two things worth guarding are that it refuses rows it
 * cannot import rather than inventing data, and that the flags it reports
 * honouring were actually honoured.
 */
class ImportUsersTest extends TestCase
{
    use RefreshDatabase;

    private function csv(string $body, string $header = "first_name,last_name,email\n"): string
    {
        $path = tempnam(sys_get_temp_dir(), 'users').'.csv';
        file_put_contents($path, $header.$body);

        return $path;
    }

    public function test_it_creates_a_user_per_row(): void
    {
        $this->artisan('users:import', ['file' => $this->csv(
            "Ada,Lovelace,ada@analytical.test\nBlaise,Pascal,blaise@wager.test\n"
        )])->assertSuccessful();

        $this->assertSame(2, User::count());
        $this->assertSame('Lovelace', User::where('email', 'ada@analytical.test')->value('last_name'));
    }

    public function test_the_flags_actually_take_effect(): void
    {
        // approval_status and email_verified_at are not in the model's
        // $fillable, and mass assignment drops what it lacks permission for
        // without a word. The first version of this importer created every
        // user pending and unverified while reporting success, which is
        // exactly the kind of thing nobody notices until a league of people
        // cannot sign in.
        $this->artisan('users:import', [
            'file' => $this->csv("Ada,Lovelace,ada@analytical.test\n"),
            '--approved' => true,
            '--verified' => true,
        ])->assertSuccessful();

        $user = User::firstOrFail();

        $this->assertSame('approved', $user->approval_status);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNotNull($user->approval_decided_at);
    }

    public function test_without_the_flags_a_user_lands_in_the_queue_unverified(): void
    {
        $this->artisan('users:import', ['file' => $this->csv("Ada,Lovelace,ada@analytical.test\n")])
            ->assertSuccessful();

        $user = User::firstOrFail();

        $this->assertSame('pending', $user->approval_status);
        $this->assertNull($user->email_verified_at);
    }

    public function test_the_password_is_hashed_and_a_shared_one_works(): void
    {
        $this->artisan('users:import', [
            'file' => $this->csv("Ada,Lovelace,ada@analytical.test\n"),
            '--password' => 'first-to-act',
        ])->assertSuccessful();

        $password = User::firstOrFail()->password;

        $this->assertNotSame('first-to-act', $password, 'A password must never be stored as given.');
        $this->assertTrue(Hash::check('first-to-act', $password));
    }

    public function test_without_a_shared_password_each_user_gets_a_different_one(): void
    {
        $this->artisan('users:import', ['file' => $this->csv(
            "Ada,Lovelace,ada@analytical.test\nBlaise,Pascal,blaise@wager.test\n"
        )])->assertSuccessful();

        $hashes = User::pluck('password');

        $this->assertCount(2, $hashes->unique(), 'Two users must not share a password hash.');
    }

    public function test_it_skips_rows_it_cannot_import_rather_than_inventing_data(): void
    {
        $this->artisan('users:import', ['file' => $this->csv(
            "Ada,Lovelace,ada@analytical.test\n".
            "Grace,,grace@nolastname.test\n".      // no surname
            "Alan,Turing,not-an-email\n".          // not an address
            "Ada,Again,ADA@analytical.test\n"      // same person, different case
        )])->assertSuccessful();

        $this->assertSame(1, User::count());
        $this->assertSame('ada@analytical.test', User::firstOrFail()->email);
    }

    public function test_a_second_run_adds_only_what_is_new(): void
    {
        $file = $this->csv("Ada,Lovelace,ada@analytical.test\n");

        $this->artisan('users:import', ['file' => $file])->assertSuccessful();
        $this->artisan('users:import', ['file' => $file])->assertSuccessful();

        $this->assertSame(1, User::count());
    }

    public function test_a_dry_run_writes_nothing(): void
    {
        $this->artisan('users:import', [
            'file' => $this->csv("Ada,Lovelace,ada@analytical.test\n"),
            '--dry-run' => true,
        ])->assertSuccessful();

        $this->assertSame(0, User::count());
    }

    public function test_named_addresses_become_administrators(): void
    {
        $this->artisan('users:import', [
            'file' => $this->csv("Ada,Lovelace,ada@analytical.test\nBlaise,Pascal,blaise@wager.test\n"),
            '--admin' => ['ADA@analytical.test'],
        ])->assertSuccessful();

        $this->assertTrue(User::where('email', 'ada@analytical.test')->value('is_admin'));
        $this->assertFalse(User::where('email', 'blaise@wager.test')->value('is_admin'));
    }

    public function test_a_file_without_the_required_columns_is_refused(): void
    {
        $path = $this->csv("Ada,ada@analytical.test\n", header: "first_name,email\n");

        $this->artisan('users:import', ['file' => $path])->assertFailed();

        $this->assertSame(0, User::count());
    }

    public function test_a_spreadsheet_header_is_understood(): void
    {
        // A BOM on the first cell and title-cased headings with spaces are what
        // Excel and Sheets actually produce.
        $path = $this->csv("Ada,Lovelace,ada@analytical.test\n", header: "\u{FEFF}First Name,Last Name,Email\n");

        $this->artisan('users:import', ['file' => $path])->assertSuccessful();

        $this->assertSame(1, User::count());
    }

    public function test_a_missing_file_fails_loudly(): void
    {
        $this->artisan('users:import', ['file' => '/no/such/file.csv'])->assertFailed();
    }
}
