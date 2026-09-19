#!/usr/bin/env bash
set -euo pipefail

APP_ROOT="${APP_ROOT:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
BACKUP_ROOT="${BACKUP_ROOT:-$APP_ROOT/storage/app/backups}"
STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP_DIR="$BACKUP_ROOT/1g-sls-$STAMP"
DB_DIR="$BACKUP_DIR/database"
FILES_DIR="$BACKUP_DIR/files"

mkdir -p "$DB_DIR" "$FILES_DIR"

env_value() {
  local key="$1"
  local file="$APP_ROOT/.env"

  if [[ ! -f "$file" ]]; then
    return 0
  fi

  grep -E "^${key}=" "$file" | tail -n 1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'$//"
}

DB_DATABASE="${DB_DATABASE:-$(env_value DB_DATABASE)}"
DB_USERNAME="${DB_USERNAME:-$(env_value DB_USERNAME)}"
DB_PASSWORD="${DB_PASSWORD:-$(env_value DB_PASSWORD)}"
DB_HOST="${DB_HOST:-$(env_value DB_HOST)}"
DB_PORT="${DB_PORT:-$(env_value DB_PORT)}"

DB_DATABASE="${DB_DATABASE:-oneg_sls}"
DB_USERNAME="${DB_USERNAME:-oneg_sls}"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"

MYSQL_PWD="$DB_PASSWORD" mysqldump \
  --host="$DB_HOST" \
  --port="$DB_PORT" \
  --user="$DB_USERNAME" \
  --single-transaction \
  --routines \
  --triggers \
  --events \
  --no-tablespaces \
  "$DB_DATABASE" | gzip -9 > "$DB_DIR/$DB_DATABASE.sql.gz"

tar -czf "$FILES_DIR/app-files.tar.gz" \
  -C "$APP_ROOT" \
  --exclude='./vendor' \
  --exclude='./node_modules' \
  --exclude='./tools' \
  --exclude='./.git' \
  --exclude='./storage/app/backups' \
  --exclude='./storage/logs' \
  --exclude='./storage/framework/cache' \
  --exclude='./storage/framework/sessions' \
  --exclude='./storage/framework/testing' \
  --exclude='./storage/framework/views/*.php' \
  .env app bootstrap config database public resources routes scripts composer.json composer.lock package.json vite.config.js artisan README.md 2>/dev/null

cat > "$BACKUP_DIR/backup-manifest.json" <<JSON
{
  "created_at": "$(date --iso-8601=seconds)",
  "app_root": "$APP_ROOT",
  "database": "$DB_DATABASE",
  "database_backup": "$DB_DIR/$DB_DATABASE.sql.gz",
  "files_backup": "$FILES_DIR/app-files.tar.gz"
}
JSON

echo "1G-SLS backup completed"
echo "Backup folder: $BACKUP_DIR"
