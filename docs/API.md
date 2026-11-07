# MovieMov API reference

Base URL: `https://moviemov.id/api` (local: `http://localhost:8000/api`).

- Send `Accept: application/json` on every request, so errors come back as JSON instead of redirects.
- Write endpoints need `Authorization: Bearer <token>` from `POST /login`. Only admin users can log in.
- Movies and categories are addressed by **slug** (`/movies/night-train`, `/categories/sci-fi`).
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
  "views": 0,
  "rating_avg": 0,
  "rating_count": 0,
  "url": "https://moviemov.id/movies/starfall-protocol",
  "created_at": "2026-11-07T07:47:08+00:00",
  "updated_at": "2026-11-07T07:47:08+00:00"
}
```

- `views`: page views on the website (one per visitor session). API reads do not count.
- `rating_avg`: average of visitor ratings, 1-5, two decimals. `0` when unrated.
- `backup_embed_urls`: extra players shown as Server 2, 3, ... (Server 1 is `embed_url`). `[]` when none.
- `url`: the Indonesian page. The English page is the same path under `/en` (e.g. `/en/movies/<slug>`).

### `GET /movies` (public)

| Query | Default | Notes |
| --- | --- | --- |
| `q` | | search in title and synopsis, max 100 chars |
| `category` | | category slug. Combine freely with `q`, `sort` and `dir`. |
| `sort` | `created` | `created` (newest), `rating` (highest), `views` (most viewed), `duration` (longest) |
| `dir` | per sort (`desc`) | `desc` or `asc` to reverse, e.g. `sort=duration&dir=asc` = shortest first. Movies with no value (e.g. no duration) are always listed last. |
| `per_page` | `24` | 1-100 |
| `page` | `1` | |

```sh
curl 'https://moviemov.id/api/movies?category=sci-fi&sort=rating&per_page=10' -H 'Accept: application/json'
```

Response: `{ "data": [Movie, ...], "links": {...}, "meta": { "current_page", "last_page", "per_page", "total", ... } }`.
An unknown `sort` or `dir` value returns `422`.

### `GET /movies/{slug}` (public)

Returns `{ "data": Movie }`, or `404`.

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
| `slug` | no | `a-z0-9-_`, unique. Generated from the title (`night-train`, then `night-train-2`, ...). |
| `synopsis` | no | max 10000 chars. The first ~155 chars become the page's meta description. |
| `release_year` | no | 1870-2100 |
| `duration_minutes` | no | 1-1440 (minutes). Shown as `1j 45m` / `1h 45m` on cards and used by `sort=duration`. |

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
  -F thumbnail=@night-train.jpg
```

With curl, use `--form-string` for `embed_url`: a `-F` value that starts with `<` is read as a file name.

Response: `201 Created` with `{ "data": Movie }`.

### `PUT|PATCH /movies/{slug}` (token)

Same fields as create, all optional. Only the fields you send change.

- `categories`: when sent, replaces the whole list (`[]` removes all). When omitted, categories stay.
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
