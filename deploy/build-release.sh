#!/usr/bin/env bash
# Build a production release zip for Hostinger (see docs/DEPLOY.md).
# Usage (git-bash, from the repo root):  bash deploy/build-release.sh
# Output: $OUT_DIR/release.zip and $OUT_DIR/deploy_<token>.php. No .env is included unless ENV_FILE is set.
set -euo pipefail

REPO="$(cd "$(dirname "$0")/.." && pwd -W 2>/dev/null || pwd)"
OUT_DIR="${OUT_DIR:-$REPO/.release}"
REL="$OUT_DIR/release"

rm -rf "$REL" && mkdir -p "$REL"

# 1. Front-end assets
docker run --rm -v "$REPO:/app" -w /app node:22-alpine sh -c "npm ci --no-audit --no-fund && npm run build"

# 2. Committed code only (HEAD), plus built assets and the root .htaccess
git -C "$REPO" archive HEAD | tar -x -C "$REL"
cp -r "$REPO/public/build" "$REL/public/"
cp "$REPO/deploy/hostinger.htaccess" "$REL/.htaccess"
rm -rf "$REL/tests" "$REL/docker" "$REL/compose.yaml" "$REL/phpunit.xml" "$REL/.editorconfig" \
       "$REL/.gitattributes" "$REL/.npmrc" "$REL/package.json" "$REL/package-lock.json" \
       "$REL/vite.config.js" "$REL/resources/css" "$REL/docs" "$REL/config/scramble.php"

# 3. Production PHP dependencies
docker run --rm -v "$REL:/app" -w /app web_movie-app composer install --no-dev --optimize-autoloader --no-interaction -q

# 4. Optional: first deploy only, ship a .env (never on redeploys; the runner keeps the server's .env anyway)
if [ -n "${ENV_FILE:-}" ]; then cp "$ENV_FILE" "$REL/.env"; fi

# 5. Zip + one-time runner with a random name
python - "$REL" "$OUT_DIR/release.zip" <<'PY'
import os, sys, zipfile
src, out = sys.argv[1], sys.argv[2]
n = 0
with zipfile.ZipFile(out, 'w', zipfile.ZIP_DEFLATED, compresslevel=6) as z:
    for root, _, files in os.walk(src):
        for f in files:
            p = os.path.join(root, f)
            z.write(p, os.path.relpath(p, src).replace(os.sep, '/'))
            n += 1
print(f"{n} files -> {out} ({os.path.getsize(out) // 1024} KB)")
PY
TOKEN="$(python -c 'import secrets; print(secrets.token_hex(16))')"
cp "$REPO/deploy/deploy-runner.php" "$OUT_DIR/deploy_$TOKEN.php"
echo "runner: $OUT_DIR/deploy_$TOKEN.php"
