#!/usr/bin/env bash
# One-command redeploy of MovieMov to Hostinger (no SSH). See docs/DEPLOY.md.
#
#   bash deploy/deploy.sh            test + lint + build + upload + run + smoke test
#   bash deploy/deploy.sh --dry-run  everything except upload/run (safe to try)
#   bash deploy/deploy.sh --skip-tests
#
# Needs: git-bash, Docker Desktop running, python, curl, and a Hostinger API token in deploy/deploy.env
# (copy deploy/deploy.env.example; that file is gitignored).
set -euo pipefail

REPO="$(cd "$(dirname "$0")/.." && pwd -W 2>/dev/null || pwd)"
cd "$REPO"

DRY_RUN=0; SKIP_TESTS=0
for arg in "$@"; do
  case "$arg" in
    --dry-run) DRY_RUN=1 ;;
    --skip-tests) SKIP_TESTS=1 ;;
    -h|--help) sed -n '2,10p' "$0"; exit 0 ;;
    *) echo "unknown option: $arg" >&2; exit 2 ;;
  esac
done

say()  { printf '\n\033[1;36m==> %s\033[0m\n' "$*"; }
fail() { printf '\n\033[1;31mDEPLOY FAILED: %s\033[0m\n' "$*" >&2; exit 1; }

# ---- config ---------------------------------------------------------------
[ -f deploy/deploy.env ] && set -a && . deploy/deploy.env && set +a
HOSTINGER_USERNAME="${HOSTINGER_USERNAME:-u878125972}"
HOSTINGER_DOMAIN="${HOSTINGER_DOMAIN:-moviemov.id}"
SITE_URL="${SITE_URL:-https://$HOSTINGER_DOMAIN}"
OUT_DIR="${OUT_DIR:-$REPO/.release}"
if [ "$DRY_RUN" = 0 ] && [ -z "${HOSTINGER_API_TOKEN:-}" ]; then
  fail "HOSTINGER_API_TOKEN missing. Copy deploy/deploy.env.example to deploy/deploy.env and paste your token (hPanel > Account > API)."
fi

# ---- human confirmation (real deploys only) ----------------------------------
# Owner decision: only the owner deploys. A real deploy needs an interactive terminal and the domain typed by hand,
# so scripts and AI agents (which run without a TTY) cannot deploy. --dry-run never uploads and skips this.
if [ "$DRY_RUN" = 0 ]; then
  if [ ! -t 0 ] || [ ! -t 1 ]; then
    fail "a real deploy must be started by a person in an interactive terminal (double-click deploy.cmd). Use --dry-run for automated checks."
  fi
  printf '\nThis will deploy to PRODUCTION (%s).\nType the domain to continue: ' "$SITE_URL"
  read -r CONFIRM
  [ "$CONFIRM" = "$HOSTINGER_DOMAIN" ] || fail "confirmation did not match; nothing was deployed."
fi

# ---- preflight --------------------------------------------------------------
say "Preflight"
docker info >/dev/null 2>&1 || fail "Docker is not running. Start Docker Desktop and try again."
if [ -n "$(git status --porcelain)" ]; then
  git status --short
  fail "uncommitted changes. Only committed code (HEAD) is deployed: commit or stash first."
fi
echo "Deploying commit $(git rev-parse --short HEAD) ($(git log -1 --format=%s)) to $SITE_URL"
if [ "$(git rev-parse HEAD)" != "$(git rev-parse '@{u}' 2>/dev/null || echo none)" ]; then
  echo "Note: HEAD is not the same as the upstream branch (not pushed yet?)."
fi

# ---- tests ----------------------------------------------------------------
if [ "$SKIP_TESTS" = 0 ]; then
  say "Tests and lint"
  docker compose run --rm --no-deps -T app sh -c "php artisan test && vendor/bin/pint --test" \
    || fail "tests or lint failed"
  rm -rf public/uploads/thumbnails/* 2>/dev/null || true
fi

# ---- build ----------------------------------------------------------------
say "Build release"
rm -rf "$OUT_DIR"
OUT_DIR="$OUT_DIR" bash deploy/build-release.sh
ZIP="$OUT_DIR/release.zip"
RUNNER_PATH="$(ls "$OUT_DIR"/deploy_*.php)"
RUNNER="$(basename "$RUNNER_PATH")"
python - "$ZIP" <<'PY' || fail "release.zip failed the safety check"
import sys, zipfile
names = zipfile.ZipFile(sys.argv[1]).namelist()
bad = [n for n in names if n == '.env' or 'admin-init' in n or n.startswith('public/uploads/')]
need = ['.htaccess', 'artisan', 'vendor/autoload.php', 'public/index.php', 'public/build/manifest.json']
missing = [n for n in need if n not in names]
if bad or missing:
    print('forbidden:', bad, 'missing:', missing); sys.exit(1)
print(f'release.zip OK ({len(names)} files, no .env, no uploads)')
PY

if [ "$DRY_RUN" = 1 ]; then
  say "Dry run finished. Nothing was uploaded. Release: $ZIP"
  exit 0
fi

# ---- upload ---------------------------------------------------------------
say "Upload to Hostinger"
CREDS="$(curl -sS --fail-with-body -X POST "https://developers.hostinger.com/api/hosting/v1/files/upload-urls" \
  -H "Authorization: Bearer $HOSTINGER_API_TOKEN" -H "Content-Type: application/json" -H "Accept: application/json" \
  -d "{\"username\":\"$HOSTINGER_USERNAME\",\"domain\":\"$HOSTINGER_DOMAIN\"}")" \
  || fail "could not get upload URL from the Hostinger API: $CREDS"
read -r TUS_URL AUTH_KEY REST_KEY < <(python -c "import json,sys; d=json.loads(sys.argv[1]); print(d['url'], d['auth_key'], d['rest_auth_key'])" "$CREDS") \
  || fail "unexpected upload-URL response"

upload() { # $1 local file, $2 path inside public_html
  local size; size=$(stat -c%s "$1")
  local code
  code=$(curl -sS -o /dev/null -w '%{http_code}' -X POST "$TUS_URL/$2?override=true" \
    -H "X-Auth: $AUTH_KEY" -H "X-Auth-Rest: $REST_KEY" -H "Tus-Resumable: 1.0.0" \
    -H "Upload-Length: $size" -H "Upload-Offset: 0")
  [ "$code" = 201 ] || fail "upload create for $2 returned $code"
  local offset
  offset=$(curl -sS -D - -o /dev/null -X PATCH "$TUS_URL/$2?override=true" \
    -H "X-Auth: $AUTH_KEY" -H "X-Auth-Rest: $REST_KEY" -H "Tus-Resumable: 1.0.0" \
    -H "Content-Type: application/offset+octet-stream" -H "Upload-Offset: 0" \
    --data-binary "@$1" | tr -d '\r' | awk -F': ' 'tolower($1)=="upload-offset"{print $2}')
  [ "$offset" = "$size" ] || fail "upload of $2 incomplete ($offset of $size bytes)"
  echo "uploaded $2 ($size bytes)"
}
upload "$ZIP" "release.zip"                 # app root, not web-accessible
upload "$RUNNER_PATH" "public/$RUNNER"      # web-accessible, runs once, deletes itself

# ---- run ------------------------------------------------------------------
say "Run deploy on the server"
LOG="$(curl -sS --max-time 600 "$SITE_URL/$RUNNER" || true)"
echo "$LOG"
echo "$LOG" | grep -q 'DEPLOY_OK' || fail "server deploy did not finish (see log above). The site may be half-updated: fix and run again."

# ---- smoke test -------------------------------------------------------------
say "Smoke test"
check() { # $1 path, $2 expected status
  local code; code=$(curl -s -o /dev/null -w '%{http_code}' "$SITE_URL$1")
  if [ "$code" = "$2" ]; then echo "ok   $code $1"; else echo "FAIL $code $1 (expected $2)"; SMOKE_FAILED=1; fi
}
SMOKE_FAILED=0
check / 200
check /sitemap.xml 200
check /robots.txt 200
check /api/movies 200
check /admin/login 200
check /.env 403
check /vendor/autoload.php 404
check /release.zip 404
check "/$RUNNER" 404
[ "$SMOKE_FAILED" = 0 ] || fail "smoke test failed"

rm -rf "$OUT_DIR"
say "Deployed $(git rev-parse --short HEAD) to $SITE_URL"
