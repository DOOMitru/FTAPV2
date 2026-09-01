# First to Act Poker

League management for a recreational poker league: seasons, venues, tournaments,
registrations, results and standings.

## Stack

Laravel 12 (PHP 8.2) · SQLite · Blade + Alpine.js · Vite

## Setup

```bash
composer setup
```

That installs dependencies, copies `.env`, generates a key, migrates, and builds
front-end assets.

Seed a realistic dataset — 100 players, 5 venues, 5 seasons, tournaments with
registrants and results:

```bash
php artisan db:seed
```

The seeded administrator is `admin@example.com` / `password`.

## Running

```bash
composer dev
```

Runs the PHP server, queue worker, log tailer and Vite together.

## Tests

```bash
composer test
```

## How the league works

- A **season** spans a date range. Exactly one season is current at a time; making
  one current automatically clears the others.
- A **tournament** belongs to a season and a venue. `scheduled_at` is the
  registration cutoff; `start_time` is when play begins.
- **Registrants** sign up for a tournament. Self-registration closes at
  `scheduled_at`. An administrator can still add a player after that from the
  registrants screen, where an entry recorded after `scheduled_at` is flagged as
  a late entry.
- **Results** award points from the shared **points structure** table, so place
  and points are never typed in by hand.
- **Venue points** are a separate loyalty ledger, independent of tournament results.

Everything under `/poker` and `/users` requires an administrator account.

## Documentation

- `docs/superpowers/specs/` — design specifications
- `docs/superpowers/plans/` — implementation plans
