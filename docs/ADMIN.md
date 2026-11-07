# MovieMov admin guide

Everything is managed through the API ([API.md](API.md)). There is no admin web panel yet.

## Your admin account

- Login email: `admin@moviemov.id`
- Password: set at deploy time and saved on the owner's PC in
  `D:/wargasipil/web_movie/storage/app/private/production-admin.txt` (never committed).
- Change it, or create another admin, from the server with
  `php artisan app:create-admin [email] --password=...` (see [DEPLOY.md](DEPLOY.md) for running commands there).

## Get a token

```sh
curl -X POST https://moviemov.id/api/login -H 'Accept: application/json' \
  -d email=admin@moviemov.id -d password=YOUR_PASSWORD
```

Copy `token` from the response and send it as `Authorization: Bearer <token>`. Keep it secret; anyone with it can
post and delete movies. Revoke it with `POST /api/logout`.

## Post a movie

1. Upload the video to your player host (e.g. vidara.to) and copy its **embed code**:
   `<iframe src="https://vidara.to/e/XXXX" ...></iframe>`. A `vidara.to/v/XXXX` link also works; it is converted.
2. Prepare a **16:9 thumbnail** (1280x720 jpg/webp, under 2 MB), or have an image URL.
3. Send it:

```sh
curl -X POST https://moviemov.id/api/movies \
  -H 'Accept: application/json' -H 'Authorization: Bearer TOKEN' \
  -F title='Night Train' \
  --form-string embed_url='<iframe src="https://vidara.to/e/XXXX" width="640" height="360" frameborder="0" allowfullscreen></iframe>' \
  -F categories='Drama, Thriller' \
  -F synopsis='A long ride through the night.' \
  -F release_year=2024 -F duration_minutes=104 \
  -F thumbnail=@night-train.jpg
```

The response's `data.url` is the public page.

## Edit or remove a movie

- Change fields: `PATCH /api/movies/{slug}` with JSON, e.g. `{"title": "Night Train (Director's Cut)"}`.
- New thumbnail file: `POST /api/movies/{slug}` with `-F thumbnail=@file.jpg`.
- Set categories: send the full list, e.g. `{"categories": ["Drama"]}`. Send `[]` to clear.
- Delete: `DELETE /api/movies/{slug}`.

## Manage categories

- List: `GET /api/categories`
- Create: `POST /api/categories` with `name` and an optional `description`
- Rename or describe: `PATCH /api/categories/{slug}` with `name`, `slug`, `description`
- Delete: `DELETE /api/categories/{slug}` (movies stay, they just lose this category)

Categories with no movies are hidden from the site menu and the sitemap.

## SEO tips

- **Titles**: use the real movie title; the year is added to the page title automatically.
- **Synopsis**: the first ~155 characters become the Google snippet. Start with the most useful sentence.
- **Category descriptions**: one or two sentences each; they become the category page's snippet.
- **Thumbnails**: always add one. They are used in search results and in link previews (WhatsApp, Facebook, X).
- **Don't change slugs** after a page is indexed; the old URL will 404.
- Submit `https://moviemov.id/sitemap.xml` once in Google Search Console. It updates itself.

## Content rules

Only post videos you have the rights to share. The site embeds the player; the video host serves the file.
