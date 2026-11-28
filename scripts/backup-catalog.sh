#!/bin/bash
# Резервная копия каталога: база и фотографии.
# Запускается по расписанию, хранит 14 последних копий.

set -u

BASE="$HOME/ivu.ru"
DATA="$BASE/data"
BACKUP="$DATA/backup"
UPLOADS="$BASE/public_html/uploads"

mkdir -p "$BACKUP"
cd "$BACKUP" || exit 1

STAMP=$(date +%F)

# база
if [ -f "$DATA/catalog.db" ]; then
    cp -f "$DATA/catalog.db" "catalog-$STAMP.db"
    echo "база сохранена: catalog-$STAMP.db"
fi

# настройки (хеш пароля и ключи)
if [ -f "$DATA/config.php" ]; then
    cp -f "$DATA/config.php" "config-$STAMP.php"
    chmod 600 "config-$STAMP.php"
fi

# фотографии — раз в неделю (по понедельникам), архивом
if [ "$(date +%u)" = "1" ] && [ -d "$UPLOADS" ]; then
    tar czf "photos-$STAMP.tar.gz" -C "$UPLOADS" . 2>/dev/null
    echo "фото упакованы: photos-$STAMP.tar.gz"
fi

# чистим старое: базы и настройки старше 30 дней, архивы фото старше 60
find "$BACKUP" -maxdepth 1 -name 'catalog-*.db' -mtime +30 -delete
find "$BACKUP" -maxdepth 1 -name 'config-*.php' -mtime +30 -delete
find "$BACKUP" -maxdepth 1 -name 'photos-*.tar.gz' -mtime +60 -delete

echo "готово. копий в папке: $(ls -1 | wc -l)"
du -sh "$BACKUP"
