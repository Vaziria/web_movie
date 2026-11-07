# web_movie: agent instructions

Read this file before working in the repo. It is the source of truth for agents (Hermes, Claude Code, Codex).
Update it whenever the owner makes a decision.

## Project

- Repo: `Vaziria/web_movie` (GitHub), default branch `main`, local clone `D:/wargasipil/web_movie`.
- Owner: Heri (GitHub Vaziria).
- Site name: MovieMov (`moviemov.id`).
- Goal: a movie website. Scope set by owner 2026-11-07:
  1. Movie website (catalog of movie posts).
  2. Playback through an embedded external player (iframe), e.g. vidara `<iframe src="https://vidara.to/e/<id>">`.
     No self-hosted video.
  3. API to upload/create new movie posts.
  4. List sorting by rating, views and created date; search.
  5. Users can rate movies.
  6. Categories, managed by admin through the API.
  7. Thumbnails (16:9) per movie.
  8. Strong SEO (sitemap, robots, meta/OG, JSON-LD, canonical URLs).
- Hermes bot for this repo: profile `web_movie`. Start it from the repo root so this file loads:
  `hermes -p web_movie --in D:/wargasipil/web_movie chat`

## Current state

- Laravel 13 (PHP >= 8.4, composer `platform.php` pinned to 8.4.1), Sanctum, Blade, MySQL 8.4.
- Front end: Blade + Tailwind CSS v4 via Vite (`resources/css/app.css`, built to `public/build`). No JavaScript.
  Flat, minimal, dark, one red accent (`accent`), `ink-*` neutrals defined in `@theme`.
- Logo: `public/favicon.svg` (red rounded square + white play triangle) + "MovieMov" wordmark. Also
  `favicon.ico`, `apple-touch-icon.png`, `og-default.png` (1200x630 share image). Placeholder until the owner sends one.
- Web routes: `/` list, `/category/{slug}` list, `/movies/{slug}` player + rating, `/sitemap.xml`, `/robots.txt`.
  Lists take `?q=` (search title/synopsis) and `?sort=created|rating|views`. Views count once per session per movie
  (web page only, not the API).
- SEO: per-page title/description, canonical (sorted variants canonicalise to the plain list; `?q=` pages are
  `noindex, follow`), OG + Twitter cards, JSON-LD (`WebSite`+`SearchAction` on home, `ItemList` on lists,
  `Movie`+`VideoObject`+`AggregateRating` and `BreadcrumbList` on movie pages). robots.txt disallows `/api/` and `?q=`.
  Production forces https URLs (`AppServiceProvider`).
- API (`/api`), see README for full usage:
  - Public: `GET /movies` (`q`, `sort`, `category`, `per_page`), `GET /movies/{slug}`, `GET /categories`,
    `GET /categories/{slug}`.
  - Admin token (Sanctum, from `POST /login`): `POST /movies`, `PUT|PATCH|POST /movies/{slug}`,
    `DELETE /movies/{slug}`, `POST /categories`, `PUT|PATCH /categories/{slug}`, `DELETE /categories/{slug}`,
    `GET /me`, `POST /logout`.
  - Movies take `categories` (array, or comma-separated string in multipart); unknown names are created.
    Deleting a category detaches it; movies stay.
  - Thumbnail: `thumbnail_url` (remote) or `thumbnail` file upload (jpg/png/webp, max 2 MB) saved to
    `public/uploads/thumbnails` (gitignored; must survive redeploys). No `storage:link`: symlink() is disabled on
    Hostinger. Old uploaded files are deleted on replace/delete.
  - `embed_url` accepts a URL or a pasted `<iframe src=...>` code; vidara `/v/<id>` is rewritten to `/e/<id>`.
- Ratings 1-5, anonymous: voter cookie + HMAC'd IP; a repeat vote from the same browser or IP updates the old one.
- Embeds: iframe without sandbox by default (`EMBED_SANDBOX`), because vidara.to returns 404 inside a sandboxed iframe.
- Admin users: `php artisan app:create-admin [email]` (email defaults to `admin@<APP_URL host>`, e.g.
  `admin@moviemov.id`; prompts for password or takes `--password=`).
- The host machine has PHP 7.2 and no working Composer/MySQL: run everything through Docker.

## Decisions

Record each decision as `- YYYY-MM-DD: <decision> (<why>)`.

- 2026-11-07: Stack = Laravel (PHP) + Blade + MySQL (simple, cheap shared hosting).
- 2026-11-07: Ratings are anonymous, one per browser/IP (owner accepts that it is easy to game).
- 2026-11-07: Upload API auth = admin user login that issues a token (Laravel Sanctum).
- 2026-11-07: Mobile-first UI. Most visitors will be on phones: base styles target small screens, larger screens
  are layered on with `sm:`/`md:`/`lg:`; touch targets are at least 44px.
- 2026-11-07: Embeds accept any `https` URL (no host allowlist). Uploaders are responsible for having the rights to
  what they post.
- 2026-11-07: Tailwind CSS for the front end (owner request). Flat + minimalist design: solid fills, 1px borders,
  no shadows/gradients, one accent colour.
- 2026-11-07: Categories (many-to-many) managed by admin via the API; category pages at `/category/{slug}` for SEO.
- 2026-11-07: Thumbnails are 16:9 and replace the earlier `poster_url` field.
- 2026-11-07: Metadata (title, synopsis, thumbnail, categories) is supplied by the uploader via the API. No TMDB/OMDb.
- 2026-11-07: Admin email defaults to `admin@moviemov.id` (owner did not want to pick one; no real mailbox needed).
- 2026-11-07: Deploy to Hostinger shared hosting `moviemov.id` on PHP 8.4 (owner approved the PHP switch).

## Deployment (Hostinger)

Full runbook: `docs/DEPLOY.md` (build with `deploy/build-release.sh`, run once via `deploy/deploy-runner.php`).
Docs for humans: `docs/API.md`, `docs/ADMIN.md`. Keep them in sync when the API changes.

- Site `moviemov.id`, account `u878125972`, docroot `/home/u878125972/domains/moviemov.id/public_html`.
- The whole app lives in `public_html`; `deploy/hostinger.htaccess` (copied to `public_html/.htaccess`) rewrites
  every request into `public/` and denies dotfiles, so `.env`, `vendor/` etc. are not served.
- MySQL: db and user `u878125972_moviemov`, host `localhost`. The password lives only in the server `.env`; never
  store it in the repo, AGENTS.md or agent memory.
- No SSH, and cron output only returns the last line: deploy by building a release zip locally
  (`deploy/build-release.sh`), uploading it plus a randomly named copy of `deploy/deploy-runner.php` into
  `public_html/public/` with the Hostinger file-upload API, and requesting the runner once over HTTPS. It extracts,
  migrates, caches and deletes itself. PHP CLI on the server (for reference): `/opt/alt/php84/usr/bin/php`.
- First deploy done 2026-11-07 (commit f8dd80c). Production admin credentials are in the gitignored
  `storage/app/private/production-admin.txt` on the owner's PC.
- Never overwrite `public_html/.env` or `public_html/public/uploads/` on redeploy.

## Commands

All via Docker (`compose.yaml`: `app` = php:8.4-cli + composer + gd, `mysql` = mysql:8.4 on host port 33060).

- First run: `cp .env.example .env && docker compose run --rm --no-deps app sh -c "composer install && php artisan key:generate"`
- Front-end deps/build: `docker run --rm -v "D:/wargasipil/web_movie:/app" -w /app node:22-alpine sh -c "npm install && npm run build"`
  (do not use `--omit=optional`: Vite's native rolldown binding is an optional dependency).
- Dev server: `docker compose up -d` (start it as a background job from Hermes), then
  `docker compose exec app php artisan migrate`; site at http://localhost:8000
- Tests: `docker compose run --rm --no-deps app php artisan test` (SQLite in-memory; tests call `withoutVite()`)
- Lint/format: `docker compose run --rm --no-deps app vendor/bin/pint --test` (drop `--test` to fix)
- Build: the `npm run build` command above

## Rules

- Inspect `git status -sb` before editing; never discard uncommitted work you did not make.
- Commit only when the owner asks. No force-push, no history rewrites on `main`.
- Never commit secrets: `.env`, API keys, DB passwords, admin credentials.
- No deploys, production data changes or paid API calls without approval.
- No pirated content: do not scrape, mirror or embed unlicensed video streams.
- After changes, run the tests/lint/build listed above and report the real results.
