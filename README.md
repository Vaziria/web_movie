# web_movie

Mobile-first movie site (Laravel 13 + Blade + MySQL). Movies play through an embedded external player
(iframe), list/search/sort by newest, rating or views, and visitors can rate 1-5 without an account.
Posts are created through an admin-only JSON API.

## Local development (Docker)

```sh
cp .env.example .env
docker compose run --rm --no-deps app sh -c "composer install && php artisan key:generate"
docker compose up -d
docker compose exec app php artisan migrate
docker compose exec app php artisan app:create-admin you@example.com   # prompts for a password
```

Open http://localhost:8000. Tests: `docker compose run --rm --no-deps app php artisan test`.

## Upload API

All requests send `Accept: application/json`.

Log in (admin users only) to get a bearer token:

```sh
curl -X POST https://moviemov.id/api/login \
  -H 'Accept: application/json' \
  -d email=you@example.com -d password=...
# => {"token":"1|abc...","token_type":"Bearer",...}
```

Create a movie:

```sh
curl -X POST https://moviemov.id/api/movies \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -H 'Authorization: Bearer 1|abc...' \
  -d '{"title":"My Movie","embed_url":"https://vidara.to/e/8SdwOuKAAk92t","synopsis":"...","poster_url":"https://.../poster.jpg","release_year":2026,"duration_minutes":110}'
```

Fields:

- `title`: required, max 255
- `embed_url`: required, `https` only. `vidara.to/v/<id>` links are rewritten to the player URL `vidara.to/e/<id>`.
- `slug`: optional, auto-generated from the title
- `synopsis`, `poster_url`, `release_year`, `duration_minutes`: optional

Other endpoints:

- `GET /api/movies?q=<search>&sort=created|rating|views&per_page=24&page=1`: public list
- `GET /api/movies/{slug}`: public detail
- `PUT|PATCH /api/movies/{slug}`: update (token)
- `DELETE /api/movies/{slug}`: delete (token)
- `GET /api/me`: current user (token)
- `POST /api/logout`: revoke the current token

Only post content you have the rights to share.
