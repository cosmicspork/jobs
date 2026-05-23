# jobs

A multi-user job aggregator and AI relevance scorer. Pulls postings from
several boards, scores each one against your target profile(s) with an LLM,
and emails you a daily digest of the listings worth your attention. Optional
agents generate tailored resumes, cover letters, and answers to application
screening questions.

This is a personal-use tool, not a SaaS — every user runs against their own
profile and their own AI spend cap. Authentication is built in but there is
no public signup; users are invited by an admin.

## What it does

- **Scrapes** job boards on an hourly cron: Hacker News "Who is hiring"
  (via Algolia), Larajobs, RemoteOK, and We Work Remotely.
- **Filters** each listing through a cheap heuristic pass (keywords,
  hard criteria) before spending tokens.
- **Scores** survivors with an LLM (Anthropic Claude by default, OpenRouter
  optional). Each listing is scored once per active *target profile* — a
  user can search across multiple career directions in parallel, and each
  target has its own positioning, target titles, and criteria.
- **Caps** per-user AI spend monthly. Listings stop being queued for that
  user once they hit the cap, and the admin gets one alert per user per
  month.
- **Digests** the relevant listings by email, on each user's local-time
  schedule, with a one-click unsubscribe link.
- **Dashboards** in Filament for listings, applications, AI cost, scraper
  health, and per-user analytics.
- **Generates**, on demand, a tailored resume PDF, a cover letter PDF,
  and draft answers to application questions for a given listing.

## Tech stack

- PHP 8.4, Laravel 13
- Filament 5 (admin UI), Livewire 4, Tailwind 4
- Laravel AI SDK (`laravel/ai`) for provider abstraction + failover
- Pest 4 for tests (feature + browser)
- SQLite by default; Redis for cache, queue, and sessions
- Laravel Nightwatch for production monitoring
- DomPDF for resume/cover-letter rendering

## Setup

Requires PHP 8.4, Composer 2, Node 20+, and Redis. A devcontainer is
included if you'd rather not install these directly.

```bash
git clone git@github.com:cosmicspork/jobs.git
cd jobs
composer run setup    # install, copy .env, key:generate, migrate, build assets
```

Then edit `.env`:

- `ANTHROPIC_API_KEY=` (or `OPENROUTER_API_KEY=` if you set `AI_PROVIDER=openrouter`)
- `MAIL_*` — anything Laravel supports; a Mailgun transport is wired up
- `ADMIN_ALERT_EMAIL=` — receives cap-reached and pipeline-health alerts
- `AI_MONTHLY_CAP_USD=` — soft per-user spend cap (defaults to $5)

Create the first admin user:

```bash
php artisan tinker --execute 'App\Models\User::factory()->create(["email" => "you@example.com", "is_admin" => true])'
```

Then log in at `/admin`, complete your profile, and add one or more target
profiles.

## Running

Local development (concurrently runs `serve`, `queue:listen`, and `pail`):

```bash
composer run dev
```

In production, configure these cron entries (or let Laravel's scheduler
handle them via a single `* * * * * php artisan schedule:run`):

| Command         | Cadence  | Purpose                                       |
|-----------------|----------|-----------------------------------------------|
| `jobs:scrape`   | hourly   | Pull listings from every enabled board        |
| `digest:send`   | minutely | Send each user's digest at their local time   |
| `exports:prune` | daily    | Delete expired user data exports              |

Manual one-offs:

- `php artisan jobs:scrape` — scrape every enabled board now
- `php artisan jobs:score` — score any unscored listings now
- `php artisan reports:monthly-usage` — email each user last month's usage report
- `php artisan ai-usage:backfill-costs` — recalculate cost for older AI usage rows

## Configuration

- **Boards** — `config/boards.php`. Disable a board by setting its
  `enabled => false`.
- **AI agents** — `config/ai.php`. Each agent (`scorer`, `resume_tailor`,
  `cover_letter`, `application_questions`) has its own provider, model,
  and optional cross-provider failover list. Failover only triggers on
  rate-limit / overload, not on cap-reached.
- **Scoring caps** — `config/scoring.php` and the per-user
  `monthly_ai_cap_usd` column (overrides the global cap).
- **Agent prompts** — each agent class in `app/Ai/Agents/` holds its own
  system prompt inline (see the `instructions()` method). Edit prompts there.

## Testing

```bash
php artisan test --compact            # full suite
php artisan test --compact --filter=ScoreListings
just check                            # pint + phpstan
```

Tests assume SQLite in-memory. Browser tests use Pest's Playwright plugin.

## License

MIT.
