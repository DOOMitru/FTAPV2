<?php

namespace App\Console\Commands;

use App\Models\PointsStructure;
use App\Models\PokerTournament;
use App\Models\PokerTournamentResult;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Facades\DB;

/**
 * Set every finish's points to what its place actually pays.
 *
 * Repairs data left by a bug: a late registration shifted every recorded
 * finish down a place and did not move its points, so a finish held the money
 * for a place it no longer occupied. The hook on PokerTournamentRegistrant now
 * does both, but rows written before that fix are still wrong, and nothing
 * recomputes them -- publish() reads the stored points, the season standings
 * sum them, and the placement notification quotes them.
 *
 * Published tournaments are EXCLUDED unless asked for. Their players have
 * already been told where they came and what they scored, so correcting one
 * silently changes a season table against a number somebody was sent. That is
 * a league decision, which is why it is a flag rather than the default.
 *
 * Reports and changes nothing until confirmed.
 */
class RepriceResults extends Command
{
    use ConfirmableTrait;

    protected $signature = 'results:reprice
        {--tournament= : Only this tournament, by id}
        {--published : Include published tournaments, whose players were already told their points}
        {--dry-run : List what would change and write nothing}
        {--force : Skip the confirmation prompt}';

    protected $description = 'Correct finishes whose points do not match their place';

    public function handle(): int
    {
        $pays = PointsStructure::pluck('points', 'place');

        if ($pays->isEmpty()) {
            $this->components->error('No points structure. Every finish would be repriced to zero; refusing.');

            return self::FAILURE;
        }

        $wrong = $this->candidates()
            ->filter(fn (PokerTournamentResult $r) => $r->points !== $this->pays($pays, $r->place));

        if ($wrong->isEmpty()) {
            $this->components->info('Nothing to do: every finish in scope holds what its place pays.');

            return self::SUCCESS;
        }

        $this->report($wrong, $pays);

        if ($this->option('dry-run')) {
            $this->newLine();
            $this->components->info('Dry run: nothing was written.');

            return self::SUCCESS;
        }

        if (! $this->confirmToProceed('Repricing '.$wrong->count().' finish(es)')) {
            return self::FAILURE;
        }

        // Grouped by tournament so each is one statement, and so a tournament
        // either comes out consistent or is not touched -- a half-repriced
        // field is worse than the stale one it replaced.
        $tournaments = $wrong->pluck('tournament_id')->unique();

        DB::transaction(function () use ($tournaments) {
            foreach ($tournaments as $id) {
                PokerTournamentResult::repriceForTournament($id);
            }
        });

        $this->components->info($wrong->count().' finish(es) repriced across '.$tournaments->count().' tournament(s).');

        return self::SUCCESS;
    }

    /** What a place pays. Absent from the structure means it pays nothing. */
    private function pays(\Illuminate\Support\Collection $pays, int $place): int
    {
        return (int) ($pays[$place] ?? 0);
    }

    /** @return \Illuminate\Support\Collection<int, PokerTournamentResult> */
    private function candidates()
    {
        return PokerTournamentResult::with('tournament:id,name,published_at')
            ->when($this->option('tournament'), fn ($q, $id) => $q->where('tournament_id', $id))
            ->when(! $this->option('published'), fn ($q) => $q->whereHas(
                'tournament', fn ($t) => $t->whereNull('published_at')
            ))
            ->get();
    }

    /** @param \Illuminate\Support\Collection<int, PokerTournamentResult> $wrong */
    private function report($wrong, \Illuminate\Support\Collection $pays): void
    {
        $this->newLine();

        foreach ($wrong->groupBy(fn ($r) => $r->tournament?->name ?? '(deleted tournament)') as $name => $rows) {
            $published = $rows->first()->tournament?->isPublished() ? '  [PUBLISHED]' : '';
            $this->line('  '.$name.$published);

            foreach ($rows->sortBy('place') as $row) {
                $this->line(sprintf(
                    '    %-26s place %2d   %5d → %5d',
                    $row->player_name, $row->place, $row->points, $this->pays($pays, $row->place)
                ));
            }

            $this->newLine();
        }

        $delta = $wrong->sum(fn ($r) => $this->pays($pays, $r->place) - $r->points);
        $this->line(sprintf('  %-22s %s', 'finishes', $wrong->count()));
        $this->line(sprintf('  %-22s %s', 'net points change', ($delta > 0 ? '+' : '').number_format($delta)));

        if (! $this->option('published')) {
            $this->newLine();
            $this->components->warn(
                'Published tournaments are excluded. --published includes them, and changes season '
                .'standings against points their players have already been notified of.'
            );
        }
    }
}
