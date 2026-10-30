#!/usr/bin/env bash
# Copia la app (app/www) a web/panel para que también se pueda usar desde el navegador / iPhone.
set -euo pipefail
cd "$(dirname "$0")/.."
rm -rf web/panel
cp -r app/www web/panel
echo "Panel copiado a web/panel"
