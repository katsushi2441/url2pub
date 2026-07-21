#!/usr/bin/env bash
# public/ を heteml(url2ai.exbridge.jp)へデプロイする。
set -euo pipefail
cd "$(dirname "$0")/.."
set -a; . /home/kojima/work/aixec/.env; set +a

REMOTE="/web/url2ai_exbridge_jp"
for f in .htaccess index.html url2pub.html url2pub.php config.php auth_common.php lib.php ajax.php history.php robots.txt sitemap.xml \
         assets/style.css assets/kurage_avatar.webp assets/kurage_avatar.png \
         assets/kurage_avatar_square.webp assets/kurage_avatar_square.png assets/ogp.png; do
  curl --fail --ftp-create-dirs -T "public/$f" "ftp://${FTP_USER}:${FTP_PASS}@${FTP_HOST}${REMOTE}/$f"
  echo "deployed public/$f"
done

# index.php -> index.html + url2pub.php へ再構成したため、古いindex.phpを残さない
# (残すと機能アプリの旧URLが動き続けてしまい、index.htmlの新ランディングと重複する)。
curl --fail -Q "DELE ${REMOTE}/index.php" "ftp://${FTP_USER}:${FTP_PASS}@${FTP_HOST}/" || true
echo "removed stale public/index.php from server"

echo "-> https://url2ai.exbridge.jp/"
