#!/usr/bin/env bash
set -euo pipefail

# Atomic symlink-swap release for KnowledgeTest on the self-hosted VPS.
# Run on the server (via GitHub Actions over SSH, or by hand for the first deploy).

BASE_DIR="/home/iw10xdevs"
RELEASES_DIR="$BASE_DIR/releases"
SHARED_DIR="$BASE_DIR/shared"
CURRENT_LINK="$BASE_DIR/public_html"
KEEP_RELEASES=5
REPO_URL="git@github.com:industryweb-zz/10xdevs.git"
BRANCH="master"

TIMESTAMP="$(date +%Y%m%d%H%M%S)"
RELEASE_DIR="$RELEASES_DIR/$TIMESTAMP"

echo "==> Cloning $BRANCH into $RELEASE_DIR"
git clone --depth 1 --branch "$BRANCH" "$REPO_URL" "$RELEASE_DIR"

echo "==> Linking shared resources"
rm -rf "$RELEASE_DIR/storage"
ln -s "$SHARED_DIR/storage" "$RELEASE_DIR/storage"
ln -s "$SHARED_DIR/.env" "$RELEASE_DIR/.env"

echo "==> Installing dependencies"
cd "$RELEASE_DIR"
composer install --no-dev --optimize-autoloader --no-interaction

echo "==> Running migrations"
php artisan migrate --force

echo "==> Caching config/routes/views"
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> Swapping public_html symlink"
ln -sfn "$RELEASE_DIR/public" "$CURRENT_LINK"

echo "==> Pruning old releases (keeping last $KEEP_RELEASES)"
cd "$RELEASES_DIR"
ls -1dt */ | tail -n +$((KEEP_RELEASES + 1)) | xargs -r rm -rf

echo "==> Release $TIMESTAMP live"
