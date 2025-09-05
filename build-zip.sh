#!/usr/bin/env bash
set -euo pipefail

PLUGIN_DIR="fed-classifieds"

# Clean previous artifacts
rm -rf "$PLUGIN_DIR" "$PLUGIN_DIR.zip"

mkdir "$PLUGIN_DIR"

cp fed-classifieds.php "$PLUGIN_DIR/"
if [ -f readme.txt ]; then
    cp readme.txt "$PLUGIN_DIR/"
fi

zip -r "${PLUGIN_DIR}.zip" "$PLUGIN_DIR" >/dev/null

# Remove the temporary directory
rm -rf "$PLUGIN_DIR"

echo "Created ${PLUGIN_DIR}.zip"
