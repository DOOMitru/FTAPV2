<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seed the users table from a CSV of first_name, last_name, email.
 *
 * Written as a command rather than a seeder because it takes a file that is
 * not in the repository, it is run once against real people, and it needs to
 * be safe to try: --dry-run reports exactly what it would do and writes
 * nothing.
 *
 * Nothing here invents data. A row missing any of the three fields is skipped
 * and named, rather than being imported with a blank surname that somebody has
 * to find later.
 */
class ImportUsers extends Command
{
    protected $signature = 'users:import
        {file : Path to a CSV with first_name, last_name and email columns}
        {--password= : Give every imported user this password. Omitted, each gets a different random one}
        {--approved : Mark them approved. Omitted, they land in the approval queue}
        {--verified : Mark their email verified. Omitted, they must verify before signing in}
        {--admin=* : Email addresses to flag as administrators}
        {--dry-run : Report what would happen and write nothing}';

    protected $description = 'Create user accounts from a CSV of first_name, last_name, email';

    /** The columns the file must provide. */
    private const REQUIRED = ['first_name', 'last_name', 'email'];

    public function handle(): int
    {
        $path = $this->argument('file');

        if (! is_readable($path)) {
            $this->error("Cannot read {$path}.");

            return self::FAILURE;
        }

        $handle = fopen($path, 'r');
        $header = fgetcsv($handle);

        if ($header === false) {
            $this->error('The file is empty.');
            fclose($handle);

            return self::FAILURE;
        }

        // Tolerant of the things a spreadsheet export does to a header row:
        // a UTF-8 BOM on the first cell, stray spaces, capitals.
        $header = array_map(
            fn ($name) => Str::of((string) $name)->replace("\u{FEFF}", '')->trim()->lower()->replace(' ', '_')->value(),
            $header
        );

        $missing = array_diff(self::REQUIRED, $header);

        if ($missing !== []) {
            $this->error('The file is missing these columns: '.implode(', ', $missing));
            $this->line('Found: '.implode(', ', $header));
            fclose($handle);

            return self::FAILURE;
        }

        $admins = array_map(fn ($email) => Str::lower(trim($email)), $this->option('admin'));

        // Every address already spoken for, so a re-run adds only what is new
        // rather than failing on the unique index halfway through.
        $existing = User::pluck('email')->map(fn ($email) => Str::lower($email))->flip();

        $rows = [];
        $skipped = [];
        $seen = [];
        $line = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $line++;

            // A trailing newline reads as a single empty cell, not end of file.
            if ($row === [null] || $row === ['']) {
                continue;
            }

            $data = [];

            foreach ($header as $index => $name) {
                $data[$name] = isset($row[$index]) ? trim((string) $row[$index]) : '';
            }

            $email = Str::lower($data['email']);

            foreach (self::REQUIRED as $field) {
                if ($data[$field] === '') {
                    $skipped[] = [$line, $data['email'] ?: '(no email)', "missing {$field}"];

                    continue 2;
                }
            }

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $skipped[] = [$line, $data['email'], 'not a valid email address'];

                continue;
            }

            if (isset($seen[$email])) {
                $skipped[] = [$line, $data['email'], 'repeated in this file (line '.$seen[$email].')'];

                continue;
            }

            if ($existing->has($email)) {
                $skipped[] = [$line, $data['email'], 'already has an account'];

                continue;
            }

            $seen[$email] = $line;

            $rows[] = [
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'nickname' => $data['nickname'] ?? null,
                'email' => $email,
                'is_admin' => in_array($email, $admins, true),
            ];
        }

        fclose($handle);

        $this->summarise($rows, $skipped, $admins);

        if ($rows === []) {
            $this->warn('Nothing to import.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->newLine();
            $this->info('Dry run: nothing was written.');

            return self::SUCCESS;
        }

        $shared = $this->option('password');
        $verified = $this->option('verified') ? now() : null;
        $status = $this->option('approved') ? 'approved' : 'pending';

        // One transaction: a file that fails halfway leaves no half-imported
        // league behind to reconcile by hand.
        DB::transaction(function () use ($rows, $shared, $verified, $status) {
            foreach ($rows as $row) {
                $user = new User([
                    'first_name' => $row['first_name'],
                    'last_name' => $row['last_name'],
                    'nickname' => $row['nickname'],
                    'email' => $row['email'],
                    // Cast to 'hashed' on the model, so this is never stored raw.
                    'password' => $shared ?: Str::password(32),
                    'is_admin' => $row['is_admin'],
                ]);

                // Assigned rather than mass-assigned. None of these three is in
                // the model's $fillable, and mass assignment drops what it is
                // not given permission for WITHOUT saying so -- the first run
                // of this importer created every user pending and unverified
                // while reporting success. Widening $fillable would open them
                // to the registration form as well, which is presumably why
                // they are shut.
                $user->approval_status = $status;
                $user->approval_decided_at = $status === 'approved' ? now() : null;
                $user->email_verified_at = $verified;

                $user->save();
            }
        });

        $this->newLine();
        $this->info(sprintf('Imported %d user%s.', count($rows), count($rows) === 1 ? '' : 's'));

        return self::SUCCESS;
    }

    /** @param  array<int, array<string, mixed>>  $rows */
    private function summarise(array $rows, array $skipped, array $admins): void
    {
        $this->newLine();
        $this->line(sprintf('<info>%d</info> to import, <comment>%d</comment> skipped.', count($rows), count($skipped)));

        if ($skipped !== []) {
            $this->newLine();
            $this->table(['Line', 'Email', 'Why'], $skipped);
        }

        $flagged = array_values(array_filter($rows, fn ($row) => $row['is_admin']));

        if ($admins !== []) {
            $this->newLine();

            foreach ($admins as $email) {
                $found = array_filter($flagged, fn ($row) => $row['email'] === $email);

                $found === []
                    ? $this->warn("--admin={$email} matched no row in this file.")
                    : $this->line("Administrator: <info>{$email}</info>");
            }
        }
    }
}
