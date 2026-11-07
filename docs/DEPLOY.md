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

The plan has no SSH, and cron jobs only return the last line of output. So a release is a zip built locally plus a
one-time PHP script (`deploy/deploy-runner.php`) that runs over HTTPS. The script:

1. extracts `release.zip` (keeps the existing `.env` and `public/uploads/`),
2. clears old `bootstrap/cache/*.php`,
3. runs `migrate --force`,
4. creates the admin if `storage/app/private/admin-init.txt` exists (first deploy only), then deletes that file,
5. runs `config:cache`, `route:cache`, `view:cache`,
6. deletes itself.

## Redeploy steps

1. Commit and push the changes; run the tests and lint first (see AGENTS.md).
2. Build the release (git-bash, repo root; uses Docker):

   ```sh
   OUT_DIR=D:/hermes/profiles/web_movie/cache/scratch bash deploy/build-release.sh
   ```

   It prints the runner name, `deploy_<random>.php`. Do **not** set `ENV_FILE` on redeploys.
3. Upload both files into `public_html/public/`:
   - Hostinger file manager, or
   - the Hostinger API `hosting_files_generate-upload-url` (TUS upload; upload to `public/release.zip` and
     `public/deploy_<random>.php`).

   They go in `public/` because the root `.htaccess` sends every request there. The runner finds `release.zip`
   in its own folder or one level up.
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

There is no automatic rollback. Check out the previous commit, rebuild with `build-release.sh`, and deploy it.
Database changes are not undone automatically.
