# MovieMov (web_movie)

Mobile-first movie site (Laravel 13 + Blade + Tailwind CSS + MySQL), in Indonesian (`/`) and English (`/en`).
Movies play through an embedded external player (iframe) with optional backup servers, and visitors can report a
broken video. Visitors can browse by category, search, sort by newest / trending / rating / most viewed / duration (either direction), and rate 1-5
without an account. Posts (publish now, draft or scheduled) and categories are managed in the admin panel (`/admin`) or
through an admin-only JSON API.

## Documentation

- [docs/API.md](docs/API.md): full API reference (fields, examples, responses, errors)
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

Base URL: `https://moviemov.id/api`. Send `Accept: application/json` on every request.

### Log in

Admin users only. Returns a bearer token for the write endpoints.

```sh
curl -X POST https://moviemov.id/api/login \
  -H 'Accept: application/json' \
  -d email=admin@moviemov.id -d password=...
# => {"token":"1|abc...","token_type":"Bearer",...}
```

### Create a movie (JSON)

```sh
curl -X POST https://moviemov.id/api/movies \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -H 'Authorization: Bearer 1|abc...' \
  -d '{
    "title": "My Movie",
    "embed_url": "<iframe src=\"https://vidara.to/e/8SdwOuKAAk92t\" width=\"640\" height=\"360\" frameborder=\"0\" allowfullscreen></iframe>",
    "thumbnail_url": "https://example.com/thumb.jpg",
    "categories": ["Action", "Drama"],
    "synopsis": "...",
    "release_year": 2026,
    "duration_minutes": 110
  }'
```

### Create a movie with a thumbnail file (multipart)

```sh
curl -X POST https://moviemov.id/api/movies \
  -H 'Accept: application/json' -H 'Authorization: Bearer 1|abc...' \
  -F title='My Movie' \
  --form-string embed_url='https://vidara.to/e/8SdwOuKAAk92t' \
  -F categories='Action, Drama' \
  -F thumbnail=@thumb.jpg
```

Use `--form-string` for `embed_url` when pasting iframe code: curl treats a `-F` value starting with `<` as a file.

### Movie fields

- `title`: required, max 255
- `embed_url`: required, `https` only. A URL or the full `<iframe src="...">` embed code (its `src` is used).
  `vidara.to/v/<id>` links are rewritten to the player URL `vidara.to/e/<id>`.
- `thumbnail_url` (remote image URL) or `thumbnail` (file: jpg/png/webp, max 2 MB). 16:9 looks best.
- `categories`: list of names (JSON array, or comma-separated in multipart). Unknown names are created.
  On update, sending `categories` replaces the list; omitting it leaves it unchanged.
- `slug`: optional, generated from the title
- `synopsis`, `release_year`, `duration_minutes`: optional

### Movie endpoints

- `GET /movies?q=<search>&category=<slug>&sort=created|rating|views&per_page=24&page=1`: public list
- `GET /movies/{slug}`: public detail
- `POST /movies`: create (token)
- `PUT|PATCH /movies/{slug}`: update with JSON (token)
- `POST /movies/{slug}`: update with multipart, e.g. to replace the thumbnail file (token)
- `DELETE /movies/{slug}`: delete (token)

### Category endpoints

- `GET /categories`: public list with `movies_count`
- `GET /categories/{slug}`: public detail
- `POST /categories`: create (token). Body: `name` (required), `slug` (optional), `description` (optional;
  shown on the category page and used as its meta description)
- `PUT|PATCH /categories/{slug}`: rename / change slug / description (token)
- `DELETE /categories/{slug}`: delete (token). Movies are kept; they just lose that category.

### Other

- `GET /me`: current user (token)
- `POST /logout`: revoke the current token

Only post content you have the rights to share.
