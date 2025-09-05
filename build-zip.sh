#!/usr/bin/env bash
set -euo pipefail

# Build main plugin archive
PLUGIN_DIR="fed-classifieds"
rm -rf "$PLUGIN_DIR" "$PLUGIN_DIR.zip"
mkdir "$PLUGIN_DIR"
cp fed-classifieds.php "$PLUGIN_DIR/"
if [ -f readme.txt ]; then
    cp readme.txt "$PLUGIN_DIR/"
fi
zip -r "${PLUGIN_DIR}.zip" "$PLUGIN_DIR" >/dev/null
rm -rf "$PLUGIN_DIR"
echo "Created ${PLUGIN_DIR}.zip"

# Build standalone aggregator archive
AGG_DIR="fed-classifieds-aggregator"
rm -rf "$AGG_DIR" "$AGG_DIR.zip"
mkdir "$AGG_DIR"
cp fed-classifieds-aggregator.php "$AGG_DIR/"
cp -r assets templates "$AGG_DIR/"
zip -r "${AGG_DIR}.zip" "$AGG_DIR" >/dev/null
rm -rf "$AGG_DIR"
echo "Created ${AGG_DIR}.zip"
