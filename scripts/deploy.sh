#!/usr/bin/env bash
set -euo pipefail

NAS="${NAS:-rickard@synstation}"
ROOT="/volume1/web/vacation"

if ! command -v rsync >/dev/null 2>&1; then
  echo "rsync saknas. Kör från WSL eller Git Bash med rsync installerat," >&2
  echo "eller kopiera manuellt med scp -r (utan --delete)." >&2
  exit 1
fi

npm run build

# --exclude=api/ är obligatoriskt: dist/ innehåller inte api/, så ett
# --delete utan undantaget raderar front controllern och tar ner API:et.
rsync -az --delete --exclude=api/ dist/ "$NAS:$ROOT/public/"
rsync -az --delete app/ "$NAS:$ROOT/app/"

echo "Klart. db/ och config.php deployas separat vid installationen."
