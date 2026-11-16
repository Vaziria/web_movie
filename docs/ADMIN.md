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
   - *Tags*: comma separated keywords that are more specific than categories: actors, country, mood, series
     (`Korea, Tom Hanks, Zombie`), max 20. New tags are created; the most used ones are listed under the box.
   - *URL slug*: leave empty to generate it from the title. Don't change it after the page is indexed.
   - **Delete movie** is at the bottom of the edit page.
3. **Reports** (badge = number of movies with open reports): visitors press "Video not working?" under the
   player. Each row shows which server failed and why. Use **Test** to open the page, **Edit** to replace the
   broken embed (or add a backup player), then **Mark fixed**. Movies with open reports also show a red
   "⚠ broken-video report" line in the movie list. One report per visitor, movie and server per day.
4. **Categories**: create, rename, set the slug and description, or delete (movies are kept).
5. **Tags**: most used first with their movie count. **Movies** shows the movies with that tag; **Edit** renames
   it, changes the slug, or deletes it (movies are kept).
6. **Account**: change your password or your login email (both ask for the current password).
7. **Log out** when you are done on a shared device.

The panel is hidden from search engines (`noindex`, and `/admin` is disallowed in robots.txt). Login is limited to
5 attempts per minute.

## Your admin account

- Login email: `admin@moviemov.id` until you change it. Use an inbox you actually read: password reset links go
  there. Change it in **Account**, or on the server with `php artisan app:admin-email <current> <new>`.
- Password: set at deploy time and saved on the owner's PC in
  `D:/wargasipil/web_movie/storage/app/private/production-admin.txt` (never committed). Change it in **Account**.
- Create another admin from the server with `php artisan app:create-admin [email] --password=...` (see
  [DEPLOY.md](DEPLOY.md) for running commands there).

### Forgot your password?

1. On https://moviemov.id/admin/login tap **Forgot password?** and enter your admin email.
2. Open the email "Reset your password" (check spam too) and tap the button. The link works once, for 60 minutes.
3. Type a new password twice (at least 8 characters). You are logged in straight away.

Only admin accounts get an email; the page shows the same message for any address, so it does not reveal who is an
admin. It allows 5 requests per minute, and one email per account per minute. Existing API tokens keep working
after a reset; revoke one with `POST /api/logout`.

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
- Set categories or tags: send the full list, e.g. `{"categories": ["Drama"], "tags": ["Korea", "Zombie"]}`. Send
  `[]` to clear.
- Delete: `DELETE /api/movies/{slug}`.

## Manage categories

- List: `GET /api/categories`
- Create: `POST /api/categories` with `name` and an optional `description`
- Rename or describe: `PATCH /api/categories/{slug}` with `name`, `slug`, `description`
- Delete: `DELETE /api/categories/{slug}` (movies stay, they just lose this category)

Categories with no movies are hidden from the site menu and the sitemap.

## Manage tags

- List: `GET /api/tags` (add `?all=1` with your token to include unused tags)
- Create: `POST /api/tags` with `name` (tags are also created by a movie's `tags` field)
- Rename: `PATCH /api/tags/{slug}` with `name` and/or `slug`
- Delete: `DELETE /api/tags/{slug}` (movies stay)

Every tag with a published movie gets a page (`/tag/korea`) in the sitemap, so tags also bring search traffic:
prefer a few well-known keywords (actor names, country, franchise) over one-off words.

## Home page

The home page shows two rows above the newest movies:

- **Trending minggu ini**: most views in the last 7 days, numbered #1-#10. Appears once movies get views.
- **Rating tertinggi**: best average rating (rated movies only).

Each row has "Lihat semua" to the full sorted list. Drafts and scheduled movies never appear.

## What visitors can filter

Category chips (Semua / Action / Drama ...), tag chips (the 15 most used tags), sort tabs (Terbaru, Trending,
Rating, Populer, Durasi; tap the active tab again to reverse) and search all combine: picking a category keeps the
sort, search and tags, and searching on a category or tag page searches inside it. Tapping tags adds them, so
`#Korea` + `#Zombie` shows movies that have **both**; tap an active tag (`#Korea ×`) to remove it. A chip only
appears once its category or tag has at least one published movie.

Google indexes the plain list, category pages and single-tag pages (`/tag/korea`). Searches and combined filters
(two tags, or a category plus a tag) are `noindex` so they never compete with those pages.

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

## Push notifications

Visitors can get a browser notification when a new movie is added:

- **Where they turn it on:** a card on the home page ("Dapat kabar saat ada film baru" → **Aktifkan notifikasi**)
  and a button in the footer of every page. The browser asks for permission once. The same button turns it off.
- **Android (Chrome, Samsung Internet), desktop Chrome, Edge, Firefox:** works straight away.
- **iPhone/iPad:** only after the visitor adds MovieMov to the Home Screen (Share → "Tambahkan ke Layar Utama") and
  opens it from there (iOS 16.4+). The site shows this hint on iPhone.

What gets sent:

- **New movies, automatically**: when a movie goes live (Publish now, or its scheduled time arrives) subscribers get
  "Film baru: <judul>" with the thumbnail; tapping it opens the movie. English visitors get it in English. Several
  movies going live in the same minute become one notification ("3 film baru di MovieMov").
- To skip one, untick **🔔 Notify push subscribers when it goes live** in the movie's Publish box (API:
  `"notify_subscribers": false`). Each movie is announced at most once, and never more than a day after it went
  live. Movies that were already live when this feature arrived are never announced.
- **Push** page in the admin panel: number of subscribers, whether the sender is running ("Sender ran 1 minute ago";
  if not, the server cron job is missing), the latest notifications with how many were delivered, and a form to
  send a **custom notification** (title, message, link to a page on the site).

Tips: one notification a day at most, or people turn them off. Test on your own phone first: turn notifications
on, then publish a movie or send a custom message.

## Visitor accounts

Visitors can make a free account at **Daftar** (`/daftar`, English `/en/register`) and log in at **Masuk**
(`/masuk`, `/en/login`), with email + password or **Lanjutkan dengan Google**. The header shows "Masuk", or the
visitor's initials/photo when logged in, which opens their **Akun** page (`/akun`): change name, change or create a
password, log out, delete the account. Forgot password: `/lupa-password` emails a reset link (it may land in spam
until the domain has an SPF record; Google login doesn't need email at all).

**Free movies for guests.** Visitors who are not logged in can open **2 different movies**; re-opening one of those
never counts again. On the next movie the player is replaced by "Masuk untuk lanjut menonton" with Google / sign-up /
log-in buttons, and after logging in they come straight back to that movie. Logged-in visitors watch without limits.

- Counted per browser (a cookie), not per IP address: many Indonesian phones share one IP, so an IP limit would
  block whole groups of people. Clearing cookies or a private tab gives a guest 2 new free movies; that's expected.
- **SEO is not affected:** search engines always arrive as a new visitor, so they always see the player, the
  VideoObject data and the full page. The locked page also keeps all its SEO data.
- Change it in **Admin → Settings**: the number (0 = no limit, nobody has to log in) and "in total" or "per day".

**Admin → Users**: every account with sign-up date, last active and movies watched. Search by name or email;
filter Google / email only / blocked / admins. Open a user to see what they watched recently, and to:

- **Block** (they are logged out on their next page and can't log in, also not with Google) or **Unblock**;
- **Send password reset link** (email accounts);
- **Delete** the account and its watch history.

Admin accounts can't be blocked or deleted there. A visitor account never gets admin rights from the panel; that
stays a server command (`php artisan app:admin-email`), on purpose.

**Admin → Stats**: active today and this month, number of users, sign-ups today; bar charts of **daily active users**
(30 days), **monthly active users** (12 months, distinct visitors) and **sign-ups per day**. Logged-in users and
guests are shown separately. A visitor counts once per day; guests are counted per browser from their second page
view, so crawlers are left out. Days are WIB. Also in the API: `GET /api/stats`.

**Admin → Settings**:

- **Google login**: Client ID and Client secret from Google Cloud (Google Auth Platform → Clients → your web
  client), and "On". The secret is stored encrypted and never shown again; leave the box empty to keep it. The page
  shows the redirect URI to put in Google Cloud: `https://moviemov.id/auth/google/callback`. The button appears on
  the site as soon as both are saved. Never send the secret in chat; if it was shared, make a new one in Google
  Cloud and delete the old one.
- **Free movies for guests** (above).
- **Contact email** shown on the privacy page for data requests.

**Privacy policy** (`/privasi`, `/en/privacy`), linked in the footer and the sign-up form: what is collected
(account, watch history, cookies, push, hashed IP for reports), why, who else is involved (Hostinger, Google, push
services, video hosts), how long, and the visitor's rights under UU PDP (No. 27/2022). Read it once and tell me if
anything should change; keep it true when features change.

## Premium player (ad-free)

Movies with a **vidara** player can get a second, ad-free player for premium members. Turn it on in **Admin →
Settings → Premium player** (it is off until you do).

What visitors see on a movie page when it is on:

- **On top:** the premium box. Premium members and admins get our own player there (no ads, no pop-ups). Everyone
  else sees "Tonton tanpa iklan" with a **Dapatkan Premium** button that opens the Premium page (`/premium`).
- **Below:** the free vidara player, as before (with its ads), still under the guest limit. For premium members it
  is folded away as "Pemutar cadangan", a backup if the ad-free player ever fails.

How a member gets premium (until payments are added): open the user in **Admin → Users** and press **+30 days**,
**+90 days** or **+1 year** (counted from today, or from the current end date if premium is still running), or pick
an end date, or **Remove**. Users with premium get a PREMIUM badge, and the list has a **Premium** filter. The
Premium page shows the **contact email** from Settings as "how to subscribe", and asks visitors to mention their
account email; without a contact email it says "Langganan segera dibuka".

How it works: our server asks vidara for the movie's direct video link and keeps it for an hour (a link lasts about
8 hours). The video itself streams from vidara's servers to the viewer, not through Hostinger. The player only
downloads after the viewer presses play and keeps about 30-60 seconds ahead, to save mobile data.

- **Google:** the movie page now tells Google where the video file is (`/stream/<movie>.m3u8`), and marks the
  premium box as paid content (like a news paywall), so this is allowed and not "cloaking". Google's own crawler
  is let into the premium player; it is checked by Google's official DNS test, so faking the crawler's name doesn't
  work. Google still decides whether to index each video, and it takes days to weeks; check **Search Console → URL
  Inspection** on a movie page after it's live.
- **Settings shows** "Last video link from vidara: OK, 2 minutes ago" (or "failed (...)"). If it says failed, the
  ad-free player shows a message and members can use the free player; nothing else breaks.
- **Risks you accepted:** the vidara address used here is the one their own player uses, not a documented API, so
  vidara can change or block it at any time. Premium views don't show vidara's ads, so they don't earn vidara
  revenue. Only use it for videos you have the rights to.

## Content rules

Only post videos you have the rights to share. The site embeds the player; the video host serves the file.
