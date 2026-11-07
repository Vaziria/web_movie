# MovieMov (web_movie)

Mobile-first movie site (Laravel 13 + Blade + Tailwind CSS + MySQL), in Indonesian (`/`) and English (`/en`).
Movies play through an embedded external player (iframe) with optional backup servers, and visitors can report a
broken video. Visitors can browse by category, search, sort by newest / trending / rating / most viewed / duration (either direction), and rate 1-5
without an account. Posts (publish now, draft or scheduled) and categories are managed in the admin panel (`/admin`) or
through an admin-only JSON API.

## Documentation

- [docs/API.md](docs/API.md): API guide with curl examples (posting movies, drafts, scheduling, errors)
- [docs/openapi.json](docs/openapi.json): OpenAPI 3.1 reference of every endpoint, field and response, generated
  from the code (see "API reference" below)
- [docs/ADMIN.md](docs/ADMIN.md): admin panel, posting movies, managing categories, SEO tips
- [docs/DEPLOY.md](docs/DEPLOY.md): Hostinger deployment; redeploy with `deploy.cmd` (one command)
- [AGENTS.md](AGENTS.md): decisions, conventions and commands for coding agents

## Local development (Docker)

```sh
cp .env.example .env
docker compose run --rm --no-deps app sh -c "composer install && php artisan key:generate"
docker run --rm -v "$PWD:/app" -w /app node:22-alpine sh -c "npm install && npm run build"
docker compose up -d
docker compose exec app php artisan migrate
docker compose exec app php artisan app:create-admin            # admin@localhost, prompts for a password
```

Open http://localhost:8000. Tests: `docker compose run --rm --no-deps app php artisan test`.

## API

Base URL `https://moviemov.id/api`. Send `Accept: application/json` on every request.

```sh
# 1. Log in (admin users only) and keep the token
TOKEN=$(curl -s -X POST https://moviemov.id/api/login -H 'Accept: application/json' \
  -d email=admin@moviemov.id -d password=YOUR_PASSWORD | jq -r .token)

# 2. Post a movie: embed_url may be the player URL or the whole <iframe> code
curl -X POST https://moviemov.id/api/movies \
  -H 'Accept: application/json' -H 'Content-Type: application/json' -H "Authorization: Bearer $TOKEN" \
  -d '{"title":"Night Train","embed_url":"<iframe src=\"https://vidara.to/e/XXXX\"></iframe>","categories":["Drama"],"duration_minutes":104}'
```

Everything else (thumbnail upload, backup players, drafts and scheduled posts, updates, categories, reports) is in
[docs/API.md](docs/API.md) and [docs/openapi.json](docs/openapi.json).

### API reference (OpenAPI)

`docs/openapi.json` is generated from the routes, validation rules and API resources by
[Scramble](https://scramble.dedoc.co) (a dev-only package; nothing of it is deployed). To read it:

- with Docker running, open http://localhost:8000/docs/api (local only), or
- load the file into https://editor.swagger.io, Postman, Insomnia or a VS Code OpenAPI extension.

After changing an API route, request or resource, regenerate it and commit the result:

```sh
docker compose exec app php artisan scramble:export
```

`tests/Feature/OpenApiSpecTest.php` fails while the committed file is out of date.

Only post content you have the rights to share.
