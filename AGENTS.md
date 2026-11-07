# web_movie: agent instructions

Read this file before working in the repo. It is the source of truth for agents (Hermes, Claude Code, Codex).
Update it whenever the owner makes a decision.

## Project

- Repo: `Vaziria/web_movie` (GitHub), default branch `main`, local clone `D:/wargasipil/web_movie`.
- Owner: Heri (GitHub Vaziria).
- Goal: a movie website. Scope set by owner 2026-11-07:
  1. Movie website (catalog of movie posts).
  2. Playback through an embedded external player (iframe), e.g. `https://vidara.to/v/<id>`. No self-hosted video.
  3. API to upload/create new movie posts.
  4. List sorting by rating, views and created date; search.
  5. Users can rate movies.
- Hermes bot for this repo: profile `web_movie`. Start it from the repo root so this file loads:
  `hermes -p web_movie --in D:/wargasipil/web_movie chat`

## Current state

- Laravel 13 (PHP >= 8.4, composer `platform.php` pinned to 8.4.1), Sanctum, Blade, MySQL 8.4. No Vite/npm build:
  CSS is plain `public/css/app.css`.
- Web: `/` list (`?q=` search on title/synopsis, `?sort=created|rating|views`), `/movies/{slug}` player + rating.
  Views count once per session per movie (web page only, not the API).
- API (`/api`): `POST /login` (admin only, returns Sanctum token), `GET /me`, `POST /logout`, `GET /movies`,
  `GET /movies/{slug}`, `POST /movies`, `PUT|PATCH /movies/{slug}`, `DELETE /movies/{slug}`. See README.
- Ratings 1-5, anonymous: voter cookie + HMAC'd IP; a repeat vote from the same browser or IP updates the old one.
- Embeds: iframe without sandbox by default (`EMBED_SANDBOX`), because vidara.to returns 404 inside a sandboxed iframe.
  `config/movies.php` rewrites `vidara.to/v/<id>` (watch page) to `vidara.to/e/<id>` (player).
- Admin users: `php artisan app:create-admin <email>` (prompts for password).
- The host machine has PHP 7.2 and no working Composer/MySQL: run everything through Docker Compose.

## Decisions

Record each decision as `- YYYY-MM-DD: <decision> (<why>)`.

- 2026-11-07: Stack = Laravel (PHP) + Blade + MySQL (simple, cheap shared hosting). The CakePHP `.gitignore` will be replaced with Laravel's.
- 2026-11-07: Ratings are anonymous, one per browser/IP (owner accepts that it is easy to game).
- 2026-11-07: Upload API auth = admin user login that issues a token (Laravel Sanctum).
- 2026-11-07: Mobile-first UI. Most visitors will be on phones: base CSS targets small screens, larger screens are added with `min-width` media queries, touch targets are at least 44px, and hover effects sit behind `@media (hover:hover)`.
- 2026-11-07: Embeds accept any `https` URL (no host allowlist). Uploaders are responsible for having the rights to what they post; render iframes sandboxed.

## Open questions for the owner

- Deploy target: Hostinger shared hosting, site `moviemov.id` (account `u878125972`, docroot
  `/home/u878125972/domains/moviemov.id/public_html`). Production MySQL db name and user are both
  `u878125972_moviemov`. The owner has the password; it goes only into the server's `.env` and is never stored in
  the repo, AGENTS.md or agent memory. Still needed: domain, PHP version on the plan, SSH/Git access.
- Metadata source: default assumption is the uploader supplies title/poster/synopsis via the API (own DB, no TMDB/OMDb).

## Commands

Fill these in once the stack exists.

All via Docker (`compose.yaml`: `app` = php:8.4-cli + composer, `mysql` = mysql:8.4 on host port 33060).

- First run: `cp .env.example .env && docker compose run --rm --no-deps app sh -c "composer install && php artisan key:generate"`
- Dev server: `docker compose up -d` (start it as a background job from Hermes), then
  `docker compose exec app php artisan migrate`; site at http://localhost:8000
- Tests: `docker compose run --rm --no-deps app php artisan test` (SQLite in-memory)
- Lint/format: `docker compose run --rm --no-deps app vendor/bin/pint --test` (drop `--test` to fix)
- Build: none (no front-end build step)

## Rules

- Inspect `git status -sb` before editing; never discard uncommitted work you did not make.
- Commit only when the owner asks. No force-push, no history rewrites on `main`.
- Never commit secrets: `.env`, `config/app.php` (CakePHP 3), `app/Config/database.php` / `core.php` (CakePHP 2),
  API keys. These paths are already in `.gitignore`.
- No deploys, production data changes or paid API calls without approval.
- No pirated content: do not scrape, mirror or embed unlicensed video streams.
- After changes, run the tests/lint/build listed above and report the real results.
