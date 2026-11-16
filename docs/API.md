# MovieMov API guide

Base URL: `https://moviemov.id/api` (local: `http://localhost:8000/api`).

This is the hands-on guide with curl examples. The complete, always-current reference is
[`openapi.json`](openapi.json) (OpenAPI 3.1, generated from the code; open it at http://localhost:8000/docs/api with
Docker running, or in https://editor.swagger.io). If the two ever disagree, `openapi.json` is right; please fix this
guide.

- Send `Accept: application/json` on every request, so errors come back as JSON instead of redirects.
- Write endpoints need `Authorization: Bearer <token>` from `POST /login`. Only admin users can log in.
- Movies, categories and tags are addressed by **slug** (`/movies/night-train`, `/categories/sci-fi`, `/tags/korea`).
- Times are ISO 8601 UTC.

## Authentication

### `POST /login`

| Field | Type | Notes |
| --- | --- | --- |
| `email` | string | required |
| `password` | string | required |
| `device_name` | string | optional label for the token, e.g. `uploader-bot` |

Rate limit: 5 attempts per minute per IP.

```sh
curl -X POST https://moviemov.id/api/login \
  -H 'Accept: application/json' \
  -d email=admin@moviemov.id -d password=YOUR_PASSWORD
```

`200 OK`

```json
{
  "token": "1|Xb3...",
  "token_type": "Bearer",
  "user": { "id": 1, "name": "Admin", "email": "admin@moviemov.id" }
}
```

Wrong password or non-admin user: `422`

```json
{ "message": "Invalid credentials.", "errors": { "email": ["Invalid credentials."] } }
```

Tokens do not expire. Revoke one with `POST /logout`.

### `GET /me` (token)

Returns `{ "id": 1, "name": "Admin", "email": "admin@moviemov.id" }`.

### `POST /logout` (token)

Revokes the token used for the request. Returns `{ "message": "Logged out." }`.

## Movies

### Movie object

```json
{
  "id": 4,
  "slug": "starfall-protocol",
  "title": "Starfall Protocol",
  "synopsis": "Sample synopsis ...",
  "thumbnail_url": "https://moviemov.id/uploads/thumbnails/GQY2Dokg....jpg",
  "embed_url": "https://vidara.to/e/8SdwOuKAAk92t",
  "backup_embed_urls": ["https://vidara.to/e/AnotherId"],
  "release_year": 2025,
  "duration_minutes": 111,
  "categories": [{ "name": "Sci-Fi", "slug": "sci-fi" }],
  "tags": [{ "name": "Korea", "slug": "korea" }, { "name": "Zombie", "slug": "zombie" }],
  "views": 0,
  "rating_avg": 0,
  "rating_count": 0,
  "status": "published",
  "published_at": "2026-11-07T07:47:08+00:00",
  "url": "https://moviemov.id/movies/starfall-protocol",
  "created_at": "2026-11-07T07:47:08+00:00",
  "updated_at": "2026-11-07T07:47:08+00:00"
}
```

- `views`: page views on the website (one per visitor session). API reads do not count.
- `rating_avg`: average of visitor ratings, 1-5, two decimals. `0` when unrated.
- `backup_embed_urls`: extra players shown as Server 2, 3, ... (Server 1 is `embed_url`). `[]` when none.
- `url`: the Indonesian page. The English page is the same path under `/en` (e.g. `/en/movies/<slug>`).
- `status`: `published` (live), `scheduled` (goes live by itself at `published_at`) or `draft` (`published_at` is
  `null`). Only published movies appear on the site, in the sitemap and in public API results.
- `views_week`: only with `sort=trending`: views in the last 7 days.

### `GET /movies` (public)

| Query | Default | Notes |
| --- | --- | --- |
| `q` | | search in title and synopsis, max 100 chars |
| `category` | | category slug. Combine freely with `q`, `tags`, `sort` and `dir`. |
| `tags` | | tag slugs, comma separated, max 5: `tags=korea,zombie`. A movie must have **all** of them. |
| `sort` | `created` | `created` (newest by publish time), `trending` (most views in the last 7 days), `rating` (highest), `views` (most viewed all time), `duration` (longest) |
| `dir` | per sort (`desc`) | `desc` or `asc` to reverse, e.g. `sort=duration&dir=asc` = shortest first. Movies with no value (e.g. no duration) are always listed last. |
| `status` | `published` | admin token only: `published`, `scheduled`, `draft` or `all`. Without an admin token anything but `published` returns `403`. |
| `per_page` | `24` | 1-100 |
| `page` | `1` | |

```sh
curl 'https://moviemov.id/api/movies?category=sci-fi&sort=rating&per_page=10' -H 'Accept: application/json'
curl 'https://moviemov.id/api/movies?tags=korea,zombie&sort=views' -H 'Accept: application/json'
```

Response: `{ "data": [Movie, ...], "links": {...}, "meta": { "current_page", "last_page", "per_page", "total", ... } }`.
An unknown `sort` or `dir` value returns `422`.

### `GET /movies/{slug}` (public)

Returns `{ "data": Movie }`, or `404`. Drafts and scheduled movies are `404` unless you send an admin token.

### `POST /movies` (token)

Fields:

| Field | Required | Rules |
| --- | --- | --- |
| `title` | yes | string, max 255 |
| `embed_url` | yes | `https` URL, max 2048. A full `<iframe src="...">` embed code is also accepted; its `src` is used. `https://vidara.to/v/<id>` is rewritten to `https://vidara.to/e/<id>`. |
| `backup_embed_urls` | no | up to 5 backup players. JSON array of `https` URLs or `<iframe>` codes; in multipart, one per line. Duplicates and copies of `embed_url` are dropped. `[]` or `null` removes them all. |
| `thumbnail_url` | no | `http`/`https` image URL. Ignored when a `thumbnail` file is uploaded in the same request. |
| `thumbnail` | no | image file (multipart only): jpg, jpeg, png, webp; max 2 MB. 16:9 recommended (e.g. 1280x720). |
| `categories` | no | list of names, max 10, each max 100 chars. JSON array, or comma-separated string in multipart (`Action, Drama`). Names are matched by slug (`sci fi` = `Sci-Fi`); unknown names create a new category. |
| `tags` | no | free keywords (actor, country, mood ...), max 20, each max 50 chars. JSON array, or comma-separated string in multipart (`Korea, Tom Hanks`). Matched by slug (`tom hanks` = `Tom Hanks`); unknown names create a new tag. Each tag gets a page at `/tag/{slug}`. |
| `slug` | no | `a-z0-9-_`, unique. Generated from the title (`night-train`, then `night-train-2`, ...). |
| `synopsis` | no | max 10000 chars. The first ~155 chars become the page's meta description. |
| `release_year` | no | 1870-2100 |
| `duration_minutes` | no | 1-1440 (minutes). Shown as `1j 45m` / `1h 45m` on cards and used by `sort=duration`. |
| `status` | no | `published` (default for new movies), `draft` or `scheduled`. On update, leaving it out keeps the current status. `published` on a movie that is already live keeps its original date. |
| `notify_subscribers` | no | boolean, default `true` for new movies: send a push notification to subscribers when the movie goes live (now, or at its scheduled time). Each movie is announced at most once. |
| `published_at` | for `scheduled` | date-time. Must be in the future for `scheduled` and not in the future for `published`. Without an offset it is read as **WIB** (`Asia/Jakarta`): `2026-11-08 20:00` = 13:00 UTC. With an offset (`2026-11-08T20:00:00+07:00`, `...Z`) it is used as given. Returned in UTC. Sending `published_at: null` without `status` makes the movie a draft. |

JSON:

```sh
curl -X POST https://moviemov.id/api/movies \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -H 'Authorization: Bearer 1|Xb3...' \
  -d '{
    "title": "Night Train",
    "embed_url": "<iframe src=\"https://vidara.to/e/8SdwOuKAAk92t\" width=\"640\" height=\"360\" frameborder=\"0\" allowfullscreen></iframe>",
    "thumbnail_url": "https://example.com/night-train.jpg",
    "categories": ["Drama", "Thriller"],
    "tags": ["Korea", "Zombie"],
    "synopsis": "A long ride through the night.",
    "release_year": 2024,
    "duration_minutes": 104
  }'
```

Multipart with a thumbnail file:

```sh
curl -X POST https://moviemov.id/api/movies \
  -H 'Accept: application/json' -H 'Authorization: Bearer 1|Xb3...' \
  -F title='Night Train' \
  --form-string embed_url='<iframe src="https://vidara.to/e/8SdwOuKAAk92t" width="640" height="360" frameborder="0" allowfullscreen></iframe>' \
  -F categories='Drama, Thriller' \
  -F tags='Korea, Zombie' \
  -F thumbnail=@night-train.jpg
```

With curl, use `--form-string` for `embed_url`: a `-F` value that starts with `<` is read as a file name.

Response: `201 Created` with `{ "data": Movie }`.

### Drafts and scheduled posts

```sh
# Save as a draft (hidden until you publish it)
curl -X POST https://moviemov.id/api/movies -H "Authorization: Bearer $TOKEN" -H 'Accept: application/json' \
  -H 'Content-Type: application/json' -d '{"title":"Night Train","embed_url":"https://vidara.to/e/XXXX","status":"draft"}'

# Publish it tomorrow at 20:00 WIB (goes live by itself, no cron needed)
curl -X PATCH https://moviemov.id/api/movies/night-train -H "Authorization: Bearer $TOKEN" -H 'Accept: application/json' \
  -H 'Content-Type: application/json' -d '{"status":"scheduled","published_at":"2026-11-09 20:00"}'

# List your drafts / scheduled posts
curl 'https://moviemov.id/api/movies?status=scheduled' -H "Authorization: Bearer $TOKEN" -H 'Accept: application/json'
```

### `PUT|PATCH /movies/{slug}` (token)

Same fields as create, all optional. Only the fields you send change.

- `categories` / `tags`: when sent, replaces the whole list (`[]` removes all). When omitted, they stay.
- `thumbnail_url: null` or `remove_thumbnail: true` removes the thumbnail.
- Changing `slug` changes the page URL.

### `POST /movies/{slug}` (token)

Same as `PATCH`, for multipart requests (PHP only reads uploaded files on `POST`). Use it to replace the
thumbnail file:

```sh
curl -X POST https://moviemov.id/api/movies/night-train \
  -H 'Accept: application/json' -H 'Authorization: Bearer 1|Xb3...' \
  -F thumbnail=@new-thumb.jpg
```

A replaced or removed uploaded thumbnail file is deleted from the server.

### `DELETE /movies/{slug}` (token)

`204 No Content`. Also deletes the movie's ratings and its uploaded thumbnail file.

## Categories

### Category object

```json
{
  "id": 3,
  "name": "Sci-Fi",
  "slug": "sci-fi",
  "description": "Space, time and robots.",
  "movies_count": 2,
  "url": "https://moviemov.id/category/sci-fi"
}
```

### `GET /categories` (public)

All categories, sorted by name: `{ "data": [Category, ...] }`.

### `GET /categories/{slug}` (public)

`{ "data": Category }`, or `404`.

### `POST /categories` (token)

| Field | Required | Rules |
| --- | --- | --- |
| `name` | yes | max 100 |
| `slug` | no | lowercase letters, digits and single dashes (`sci-fi`), unique. Generated from the name. |
| `description` | no | max 2000. Shown on the category page and used as its meta description, so write 1-2 useful sentences. |

```sh
curl -X POST https://moviemov.id/api/categories \
  -H 'Accept: application/json' -H 'Authorization: Bearer 1|Xb3...' \
  -d name='Sci-Fi' -d description='Space, time and robots.'
```

`201 Created` with `{ "data": Category }`. A duplicate slug returns `422`.

### `PUT|PATCH /categories/{slug}` (token)

Change `name`, `slug` and/or `description`. Changing `slug` changes the category page URL.

### `DELETE /categories/{slug}` (token)

`204 No Content`. The category is removed from its movies; the movies themselves are kept.

## Tags

Free keywords on movies (actor, country, mood ...). Usually you never call these endpoints: posting a movie with
`"tags": ["Korea"]` creates the tag. Each tag has a page at `/tag/{slug}` (and `/en/tag/{slug}`), listed in the
sitemap once it has a published movie.

### Tag object

```json
{
  "id": 3,
  "name": "Korea",
  "slug": "korea",
  "movies_count": 12,
  "url": "https://moviemov.id/tag/korea"
}
```

`movies_count` counts published movies only.

### `GET /tags` (public)

Tags with at least one published movie, most used first: `{ "data": [Tag, ...] }`. With an admin token, `?all=1`
also lists unused tags.

### `GET /tags/{slug}` (public)

`{ "data": Tag }`, or `404`.

### `POST /tags` (token)

`name` (required, max 50) and optional `slug` (generated from the name). `201 Created`; a duplicate slug returns `422`.

### `PUT|PATCH /tags/{slug}` (token)

Rename it or change its `slug` (this changes the tag page URL).

### `DELETE /tags/{slug}` (token)

`204 No Content`. The tag is removed from its movies; the movies themselves are kept.

## Broken video reports (token)

Visitors can press "Video not working?" under the player. Reports are grouped per movie.

### `GET /reports`

Open reports, most reported movie first:

```json
{
  "data": [
    {
      "movie": { "slug": "night-train", "title": "Night Train" },
      "open_reports": 3,
      "servers": { "1": 2, "2": 1 },
      "reasons": { "not_loading": 2, "wrong_video": 1 },
      "latest_at": "2026-11-08T03:12:00+00:00"
    }
  ]
}
```

Reasons: `not_loading`, `wrong_video`, `bad_quality`, `other`.

### `POST /reports/{movie_slug}/resolve`

Marks every open report of that movie as fixed: `{ "resolved": 3 }`.

## Push notifications (token)

Visitors who tapped "Aktifkan notifikasi" get browser notifications. New movies are announced automatically when
they go live (unless `notify_subscribers` was `false`); several movies going live in the same minute become one
notification. A job on the server sends everything once a minute.

### `GET /push`

```json
{
  "enabled": true,
  "subscribers": 152,
  "last_run_at": "2026-11-15T09:02:35+00:00",
  "messages": [
    { "id": 7, "kind": "movie", "label": "New movie: Night Train", "status": "sent",
      "sent": 150, "failed": 0, "expired": 2, "created_at": "...", "sent_at": "..." }
  ]
}
```

`enabled` is false until the server has its keys. `last_run_at` older than a few minutes means the cron job is not
running. `kind`: `movie`, `movies` (several at once) or `custom`. `expired`: browsers that unsubscribed (removed).

### `POST /push/messages`

Send a custom notification to every subscriber, e.g. a weekly round-up.

| Field | Required | Rules |
| --- | --- | --- |
| `title` | yes | max 100 |
| `body` | no | max 200 |
| `url` | no | page that opens on tap: a path on this site (`/category/drama`) or a full URL on this site. Default `/`. |

```sh
curl -X POST https://moviemov.id/api/push/messages \
  -H 'Accept: application/json' -H 'Content-Type: application/json' -H "Authorization: Bearer ***" \
  -d '{"title":"Film baru minggu ini","body":"5 film baru sudah bisa ditonton.","url":"/"}'
```

`201` with the queued message; it goes out within a minute. `409` when push is not set up on the server.

## Users & statistics (token)

Visitor accounts and active-user numbers, read-only. Blocking, deleting and reset links are in the admin panel.

### `GET /users`

Newest first, 50 per page. `?q=` searches name and email, `?page=2`. `premium_until` is null for users without
premium (premium is set in the admin panel, Admin → Users).

```json
{
  "data": [
    { "id": 12, "name": "Siti", "email": "siti@gmail.com", "google": true, "is_admin": false, "blocked": false,
      "premium_until": "2026-12-16T10:00:00+00:00",
      "watches_count": 7, "created_at": "2026-11-15T10:00:00+00:00", "last_active_at": "2026-11-15T12:30:00+00:00" }
  ],
  "meta": { "current_page": 1, "last_page": 1, "total": 1 }
}
```

### `GET /stats`

```json
{
  "totals": { "users_total": 40, "users_blocked": 0, "google_users": 31, "signups_today": 3,
              "active_today_users": 12, "active_today_guests": 210, "active_month_users": 35, "active_month_guests": 2400 },
  "daily":   [ { "day": "2026-11-15", "users": 12, "guests": 210, "signups": 3 } ],
  "monthly": [ { "month": "2026-11", "users": 35, "guests": 2400 } ]
}
```

`daily` = the last 30 days, `monthly` = the last 12 months (distinct visitors), oldest first. Days are WIB. Guests
are counted per browser; crawlers are not counted.

## Errors

| Status | Meaning | Body |
| --- | --- | --- |
| `401` | missing or invalid token | `{ "message": "Unauthenticated." }` |
| `403` | logged in but not an admin | `{ "message": "This action is unauthorized." }` |
| `404` | unknown slug | `{ "message": "..." }` |
| `422` | validation failed | `{ "message": "...", "errors": { "field": ["..."] } }` |
| `429` | rate limited (login: 5/min) | `{ "message": "Too Many Attempts." }` |

Example `422`:

```json
{
  "message": "The embed url field must be a valid URL.",
  "errors": { "embed_url": ["The embed url field must be a valid URL."] }
}
```
