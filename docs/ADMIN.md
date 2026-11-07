# MovieMov admin guide

Two ways to manage the site:

- **Admin panel** (browser, works on phones): https://moviemov.id/admin
- **API** for scripts and bots: [API.md](API.md)

## Admin panel

1. Open https://moviemov.id/admin and log in with the admin email and password.
2. **Movies**: list with search and a category filter (incl. "No category", to find movies you forgot to tag); **+ New movie** opens the form:
   - *Embed code or player URL*: paste the full vidara `<iframe ...>` code (or an https URL).
   - *Duration (minutes)*: e.g. `105`. Shown as "1j 45m" and used by the **Durasi** sort, so fill it in;
     movies without it go last when sorting by duration.
   - *Publish*: **Publish now**, **Draft** (hidden from visitors; you can open it with *Preview*), or **Schedule**
     with a date and time in WIB. A scheduled movie goes live by itself at that time; nothing else to run.
     Drafts and scheduled movies show "● Draft" / "◷ Scheduled · <time>" in the movie list, which can also be
     filtered by status.
   - *Backup players*: optional, one per line (iframe code or https URL, max 5). Visitors see buttons
     "Server 1, 2, 3 ..." under the player and can switch when one host is down.
   - *Thumbnail*: upload a 16:9 image (jpg/png/webp, max 2 MB) or give an image URL. Leaving both empty keeps the
     current one; tick *Remove current thumbnail* to delete it.
   - *Categories*: comma separated (`Action, Drama`). New names are created; existing names are listed under the box.
   - *URL slug*: leave empty to generate it from the title. Don't change it after the page is indexed.
   - **Delete movie** is at the bottom of the edit page.
3. **Reports** (badge = number of movies with open reports): visitors press "Video not working?" under the
   player. Each row shows which server failed and why. Use **Test** to open the page, **Edit** to replace the
   broken embed (or add a backup player), then **Mark fixed**. Movies with open reports also show a red
   "⚠ broken-video report" line in the movie list. One report per visitor, movie and server per day.
4. **Categories**: create, rename, set the slug and description, or delete (movies are kept).
5. **Log out** when you are done on a shared device.

The panel is hidden from search engines (`noindex`, and `/admin` is disallowed in robots.txt). Login is limited to
5 attempts per minute.

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

## Home page

The home page shows two rows above the newest movies:

- **Trending minggu ini**: most views in the last 7 days, numbered #1-#10. Appears once movies get views.
- **Rating tertinggi**: best average rating (rated movies only).

Each row has "Lihat semua" to the full sorted list. Drafts and scheduled movies never appear.

## What visitors can filter

Category chips (Semua / Action / Drama ...), sort tabs (Terbaru, Trending, Rating, Populer, Durasi; tap the active
tab again to reverse) and search all combine: picking a category keeps the sort and search, and searching on a
category page searches inside that category. A chip only appears once its category has at least one movie.

## Languages

The public site is **Indonesian at `https://moviemov.id/`** and **English at `https://moviemov.id/en/`**, with an
ID / EN switch in the header. Only the site's own texts are translated; movie titles, synopses and category names
are shown as you write them, so write them in Indonesian. Google gets both versions with `hreflang` links and both
are in the sitemap. The admin panel is in English.

## SEO tips

- **Titles**: use the real movie title; the year is added to the page title automatically.
- **Synopsis**: the first ~155 characters become the Google snippet. Start with the most useful sentence.
- **Category descriptions**: one or two sentences each; they become the category page's snippet.
- **Thumbnails**: always add one. They are used in search results and in link previews (WhatsApp, Facebook, X).
- **Don't change slugs** after a page is indexed; the old URL will 404.
- Submit `https://moviemov.id/sitemap.xml` once in Google Search Console. It updates itself.

## Content rules

Only post videos you have the rights to share. The site embeds the player; the video host serves the file.
