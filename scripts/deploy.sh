#!/usr/bin/env bash
# public/ を heteml(url2ai.exbridge.jp)へデプロイする。
set -euo pipefail
cd "$(dirname "$0")/.."
set -a; . /home/kojima/work/aixec/.env; set +a

REMOTE="/web/url2ai_exbridge_jp"
for f in index.php config.php assets/kurage_avatar.webp assets/kurage_avatar.png; do
  curl --fail --ftp-create-dirs -T "public/$f" "ftp://${FTP_USER}:${FTP_PASS}@${FTP_HOST}${REMOTE}/$f"
  echo "deployed public/$f"
done
echo "-> https://url2ai.exbridge.jp/"
