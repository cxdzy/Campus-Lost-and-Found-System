#!/usr/bin/env bash
set -euo pipefail

# Deploy script for Campus Lost Found
# Usage: run this on the server as a post-deploy hook (Dokploy post-deploy)
# It pulls latest, installs PHP deps, builds frontend, links storage, runs migrations, clears caches, and restarts services.

APP_DIR=$(cd "$(dirname "$0")/.." && pwd)
cd "$APP_DIR"

echo "Starting deploy in $APP_DIR"

# Ensure we have a clean working copy and update
if [ -d .git ]; then
  git fetch --all --prune
  git reset --hard origin/main
else
  echo "No .git directory — skipping git operations"
fi

# Install PHP dependencies
if command -v composer >/dev/null 2>&1; then
  composer install --no-dev --prefer-dist --optimize-autoloader
else
  echo "composer not found — ensure composer is installed on the server or build in CI"
fi

# Frontend build: ensure NODE env vars are present during build (VITE_APP_NAME etc.)
# Load nvm if available so the pinned Node version (.nvmrc = 22) is used
export NVM_DIR="${NVM_DIR:-$HOME/.nvm}"
if [ -s "$NVM_DIR/nvm.sh" ]; then
  # shellcheck disable=SC1091
  source "$NVM_DIR/nvm.sh"
  nvm use --no-use 2>/dev/null || true   # honour .nvmrc without install noise
fi

if command -v npm >/dev/null 2>&1; then
  NODE_VER=$(node --version 2>/dev/null || echo "unknown")
  echo "Building frontend with Node $NODE_VER"
  npm ci --silent
  npm run build --silent
else
  echo "npm not found — skip frontend build (you should build assets in CI and deploy public/build)"
fi

# Create storage symlink if missing
php artisan storage:link || true

# Run migrations (force in production)
php artisan migrate --force || true

# Seed reference data (categories, default admin) — safe to re-run, uses updateOrInsert
php artisan db:seed --force || true

# Fetch remote images into local public storage so the site serves them reliably
if php artisan list --format=txt 2>/dev/null | grep -q "items:fetch-remote-images"; then
  php artisan items:fetch-remote-images --limit=200 || true
else
  echo "items:fetch-remote-images command not available yet; skipping image migration"
fi

# Fix any stored image files that are missing their file extension
if php artisan list --format=txt 2>/dev/null | grep -q "items:fix-extensions"; then
  php artisan items:fix-extensions || true
else
  echo "items:fix-extensions command not available yet; skipping"
fi

# Clear and rebuild caches
php artisan config:clear || true
php artisan cache:clear || true
php artisan view:clear || true
php artisan route:clear || true
php artisan config:cache || true

# Patch client_max_body_size into /nginx.conf (Dokploy container uses a single flat config).
# The check makes this idempotent — safe to run on every redeploy.
if [ -f /nginx.conf ] && ! grep -q 'client_max_body_size' /nginx.conf; then
  echo "Patching /nginx.conf: adding client_max_body_size 20M"
  sed -i 's/http {/http {\n    client_max_body_size 20M;/' /nginx.conf
fi
# Reload nginx to apply (works whether managed by systemctl or run directly in-container)
if command -v nginx >/dev/null 2>&1; then
  nginx -s reload 2>/dev/null || true
fi
if command -v systemctl >/dev/null 2>&1; then
  sudo systemctl reload nginx 2>/dev/null || true
fi

echo "Deploy complete"
