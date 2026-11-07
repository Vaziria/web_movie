# Deploying MovieMov to Hostinger

Target: Hostinger shared hosting, site `moviemov.id`, account `u878125972`.

- Document root: `/home/u878125972/domains/moviemov.id/public_html`
- PHP: 8.4 (hPanel → Advanced → PHP Configuration). Laravel 13 needs PHP 8.4+.
- MySQL: database and user `u878125972_moviemov`, host `localhost`. The password is only in the server `.env`.

## How it is laid out

The whole Laravel app lives in `public_html`. `public_html/.htaccess` (from `deploy/hostinger.htaccess`) rewrites
every request into `public/` and blocks dotfiles, so `.env`, `vendor/`, `storage/` and source files are never
served. Checked after the first deploy: `/.env` → 403, `/vendor/autoload.php`, `/composer.json`, `/artisan` → 404.

Uploaded thumbnails live in `public_html/public/uploads/thumbnails` (no `storage:link`; `symlink()` is disabled
on this plan).

## Why the deploy works this way

(2026-11-07 incident: an earlier runner version, placed in `public/` next to the zip, used `public/` as the app root
and extracted the whole app there, which replaced `public/.htaccess` and broke the home page with a 500 for ~5
minutes. The current runner finds the real root, refuses to run artisan without a `.env`, and removes anything in
`public/` that is not part of the release, keeping `uploads/`.)

The plan has no SSH, and cron jobs only return the last line of output. So a release is a zip built locally plus a
one-time PHP script (`deploy/deploy-runner.php`) that runs over HTTPS. The script:

1. removes stray entries in `public/` that the release does not contain (keeps `uploads/`),
2. extracts `release.zip` (keeps the existing `.env` and `public/uploads/`),
3. clears old `bootstrap/cache/*.php`,
4. runs `migrate --force`,
5. creates the admin if `storage/app/private/admin-init.txt` exists (first deploy only), then deletes that file,
6. runs `config:cache`, `route:cache`, `view:cache`,
7. deletes itself.

## One-command redeploy (recommended)

**Only the owner deploys.** AI agents working on this repo have no Hostinger access and must not deploy; they stop
at `deploy.cmd --dry-run`. A real deploy needs an interactive terminal and the domain typed by hand, so it cannot be
started by a script or an agent.

One-time setup:

1. Install/keep running: **Git for Windows** (git-bash), **Docker Desktop**, **Python**.
2. Create a Hostinger API token: hPanel → your account (top right) → **API** → new token.
3. `copy deploy\deploy.env.example deploy\deploy.env` and paste the token after `HOSTINGER_API_TOKEN=`.
   `deploy/deploy.env` is gitignored; never commit or share it.

Every deploy:

1. Commit your changes (only committed code is deployed; the script refuses a dirty tree).
2. Run **`deploy.cmd`** (double-click it in the repo folder), or in git-bash: `bash deploy/deploy.sh`.

What it does, stopping at the first problem:

1. checks Docker is running and the git tree is clean,
2. runs the tests and the formatter check (`--skip-tests` to skip),
3. builds `release.zip` (`deploy/build-release.sh`) and checks it has no `.env` or uploads,
4. gets an upload URL from the Hostinger API and uploads `release.zip` (to `public_html/`) and a one-time
   `deploy_<random>.php` (to `public_html/public/`),
5. runs the runner over HTTPS and requires `DEPLOY_OK`,
6. smoke-tests the live site (home, sitemap, robots, API, admin login 200; `/.env` 403; `vendor/`, the zip and the
   runner 404).

Options: `deploy.cmd --dry-run` (steps 1-3 only, uploads nothing; safe to try anytime), `--skip-tests`.

## Manual redeploy steps (fallback)

1. Commit and push the changes; run the tests and lint first (see AGENTS.md).
2. Build the release (git-bash, repo root; uses Docker):

   ```sh
   OUT_DIR=D:/hermes/profiles/web_movie/cache/scratch bash deploy/build-release.sh
   ```

   It prints the runner name, `deploy_<random>.php`. Do **not** set `ENV_FILE` on redeploys.
3. Upload the two files (Hostinger file manager, or the API `hosting_files_generate-upload-url` + TUS upload):
   - `release.zip` to `public_html/release.zip` (the app root; not web-accessible)
   - `deploy_<random>.php` to `public_html/public/deploy_<random>.php`

   The runner treats the top-most folder (itself or up to 3 parents) that contains `artisan` as the app root, and
   looks for `release.zip` there first. Never extract the release yourself into `public/`.
4. Open `https://moviemov.id/deploy_<random>.php` once and read the log. It must end with `DEPLOY_OK`.
5. Check: home page, a movie page, `/sitemap.xml`, `/robots.txt`, `/api/movies`, and that `/.env` returns 403.

If the runner prints `ERROR`, the site may be half-updated: fix the cause and deploy again. Migrations that already
ran are skipped.

## First deploy (done 2026-11-07)

Same as above, but `release.zip` also contained a production `.env` (generated `APP_KEY`, `APP_ENV=production`,
`APP_DEBUG=false`, `APP_URL=https://moviemov.id`, `SESSION_SECURE_COOKIE=true`, DB credentials) and
`storage/app/private/admin-init.txt` with a random admin password. Both files were uploaded to `public_html/`
(before `.htaccess` existed). The placeholder `default.php` was removed.

## Useful hPanel settings

- SSL: keep "Force HTTPS" on (http redirects to https).
- PHP extensions needed: pdo_mysql (nd_pdo_mysql), mbstring, openssl, fileinfo, gd, zip, intl. All enabled by default.

## Rollback

There is no automatic rollback. Check out the previous commit (`git switch --detach <commit>`), run `deploy.cmd`,
then `git switch main`.
Database changes are not undone automatically.
