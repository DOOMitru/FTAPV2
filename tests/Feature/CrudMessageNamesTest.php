<?php

namespace Tests\Feature;

use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * A CRUD message says WHICH one.
 *
 * "Sponsor deleted successfully!" is a receipt for an action the reader has
 * just taken and cannot check: with two rows a click apart, the only thing
 * that tells them they deleted the right one is the name. Twenty-one of these
 * shipped without it.
 *
 * The name is marked with emph(), which the flash renderer turns into a
 * <strong> -- never HTML in the message itself, because confirm.ts reads a
 * message as a VALUE and a sponsor called '<img onerror=...>' would otherwise
 * be markup by the time it reached the page.
 */
class CrudMessageNamesTest extends TestCase
{
    /**
     * Phrases that were the whole message before this rule existed.
     *
     * Kept as a list rather than a regex on "success": the point is not that
     * the word is banned, it is that these exact anonymous receipts are.
     */
    private const ANONYMOUS = [
        'added successfully',
        'created successfully',
        'updated successfully',
        'deleted successfully',
    ];

    /** @return array<int, string> every controller file, keyed by nothing */
    private function controllers(): array
    {
        $files = [];

        foreach (Finder::create()->files()->in(app_path('Http/Controllers'))->name('*.php') as $file) {
            $files[] = $file->getRealPath();
        }

        return $files;
    }

    public function test_no_crud_message_is_anonymous(): void
    {
        $offenders = [];
        $root = app_path().DIRECTORY_SEPARATOR;

        foreach ($this->controllers() as $path) {
            foreach (file($path, FILE_IGNORE_NEW_LINES) as $index => $line) {
                foreach (self::ANONYMOUS as $phrase) {
                    if (str_contains($line, $phrase)) {
                        $offenders[] = sprintf(
                            '%s:%d — %s', str_replace($root, '', $path), $index + 1, trim($line)
                        );
                    }
                }
            }
        }

        $this->assertSame([], $offenders, implode("\n  ", array_merge(
            ['A CRUD message names no entity, so it cannot confirm which one was acted on:'],
            $offenders
        )));
    }

    public function test_every_entity_name_in_a_message_is_marked(): void
    {
        // A message that interpolates a name must mark it, or the treatment
        // stops at whichever messages happened to get it first and the page
        // reads inconsistently -- some names bold, some not.
        //
        // Scoped to __() calls that carry a parameter array, and within those
        // only to placeholders the MESSAGE actually uses. An earlier draft
        // matched any line of the form "'name' => ...", which is the shape of
        // every associative array in the codebase -- view data, eager-load
        // maps and validation rules all reported as offences.
        $offenders = [];
        $root = app_path().DIRECTORY_SEPARATOR;
        $keys = ['name', 'tournament', 'venue', 'season', 'sponsor', 'player'];

        foreach ($this->controllers() as $path) {
            $source = file_get_contents($path);

            // A message is one quoted literal, or several joined by '.' -- the
            // longest of them is built that way, and a pattern that allowed
            // only one matched none of it and reported the file clean.
            $literal = "'(?:[^'\\\\\n]|\\\\.)*'";

            // No /s, and the literal excludes newlines on purpose. With
            // dotall the (?:[^']|\\')* run crossed lines, so the first __( in
            // a file swallowed every call after it and matched a parameter
            // array belonging to a different message -- one bogus match that
            // reported the file clean however many offences it held. The
            // params class does not need the flag: [^\]] matches newlines with
            // or without it.
            preg_match_all(
                '/__\(\s*(?<msg>'.$literal.'(?:\s*\.\s*'.$literal.')*)\s*,\s*\[(?<params>[^\]]*)\]/',
                $source, $calls, PREG_SET_ORDER
            );

            foreach ($calls as $call) {
                foreach ($keys as $key) {
                    if (! str_contains($call['msg'], ':'.$key)) {
                        continue;
                    }

                    if (! preg_match("/'{$key}'\s*=>\s*(?<value>[^,\n]+)/", $call['params'], $m)) {
                        continue;
                    }

                    $value = trim($m['value']);

                    // A number or an already-marked value is fine. So is a
                    // literal: a message that hard-codes its own subject has
                    // nothing to look up and nothing to get wrong.
                    if (str_starts_with($value, 'emph(') || str_starts_with($value, "'")) {
                        continue;
                    }

                    $offenders[] = sprintf(
                        '%s — :%s is bound to %s without emph()',
                        str_replace($root, '', $path), $key, $value
                    );
                }
            }
        }

        $this->assertSame([], $offenders, implode("\n  ", array_merge(
            ['An entity name reaches a message unmarked, so it will not be set apart:'],
            $offenders
        )));
    }
}
