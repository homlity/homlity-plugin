#!/usr/bin/env bash
# release.sh — Empaqueta una nueva versión de homlity-plugin
#
# Uso:
#   ./release.sh patch              → incrementa patch (11.1.3 → 11.1.4)
#   ./release.sh minor              → incrementa minor  (11.1.3 → 11.2.0)
#   ./release.sh major              → incrementa major  (11.1.3 → 12.0.0)
#   ./release.sh 11.2.0             → fuerza versión exacta
#   ./release.sh patch --dry-run    → muestra qué haría sin modificar nada
#
# El ZIP se genera en <wordpress-root>/dist/homlity-plugin-X.Y.Z.zip

set -euo pipefail

PLUGIN_SLUG="homlity-plugin"
PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_FILE="$PLUGIN_DIR/plugin-inmobiliario.php"
README_FILE="$PLUGIN_DIR/readme.txt"
WP_ROOT="$(cd "$PLUGIN_DIR/../../.." && pwd)"
DIST_DIR="$WP_ROOT/dist"

BUMP="${1:-patch}"
DRY_RUN=false
[[ "${2:-}" == "--dry-run" ]] && DRY_RUN=true

# ── Leer versión actual del header del plugin ────────────────────────────────
CURRENT_VERSION=$(grep -m1 '^ \* Version:' "$PLUGIN_FILE" | sed 's/.*Version:[[:space:]]*//' | tr -d '[:space:]')

if [[ -z "$CURRENT_VERSION" ]]; then
    echo "❌  No se encontró ' * Version:' en $PLUGIN_FILE" >&2
    exit 1
fi

# ── Calcular nueva versión ───────────────────────────────────────────────────
if [[ "$BUMP" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
    # Versión explícita: ./release.sh 12.0.0
    NEW_VERSION="$BUMP"
else
    IFS='.' read -r MAJOR MINOR PATCH <<< "$CURRENT_VERSION"
    case "$BUMP" in
        major) MAJOR=$((MAJOR + 1)); MINOR=0; PATCH=0 ;;
        minor) MINOR=$((MINOR + 1)); PATCH=0 ;;
        patch) PATCH=$((PATCH + 1)) ;;
        *)
            echo "❌  Uso: $0 [major|minor|patch|X.Y.Z] [--dry-run]" >&2
            exit 1 ;;
    esac
    NEW_VERSION="$MAJOR.$MINOR.$PATCH"
fi

ZIP_PATH="$DIST_DIR/${PLUGIN_SLUG}-${NEW_VERSION}.zip"

echo ""
echo "  Plugin  : $PLUGIN_SLUG"
echo "  Versión : $CURRENT_VERSION  →  $NEW_VERSION"
echo "  Salida  : $ZIP_PATH"
echo ""

if [[ "$DRY_RUN" == true ]]; then
    echo "🔍  Dry run — no se modifica nada."
    exit 0
fi

# ── Sincronizar versiones en los tres sitios ─────────────────────────────────
# 1. Header del plugin (BSD sed -i '' para macOS)
sed -i '' "s/^ \* Version:.*/ * Version:     $NEW_VERSION/" "$PLUGIN_FILE"

# 2. Constante HOMLITY_PLUGIN_VERSION
sed -i '' "s/define('HOMLITY_PLUGIN_VERSION', '[^']*')/define('HOMLITY_PLUGIN_VERSION', '$NEW_VERSION')/" "$PLUGIN_FILE"

# 3. Stable tag en readme.txt
sed -i '' "s/^Stable tag:.*/Stable tag: $NEW_VERSION/" "$README_FILE"

echo "✅  Versiones sincronizadas a $NEW_VERSION"

# ── Crear ZIP limpio (sin .git ni archivos de desarrollo) ────────────────────
mkdir -p "$DIST_DIR"

TMP_DIR=$(mktemp -d)
TMP_PLUGIN="$TMP_DIR/$PLUGIN_SLUG"

rsync -a \
    --exclude='.git' \
    --exclude='.gitignore' \
    --exclude='.gitattributes' \
    --exclude='.github' \
    --exclude='.distignore' \
    --exclude='release.sh' \
    --exclude='node_modules' \
    --exclude='.DS_Store' \
    --exclude='Thumbs.db' \
    --exclude='*.log' \
    "$PLUGIN_DIR/" "$TMP_PLUGIN/"

cd "$TMP_DIR"
zip -r "$ZIP_PATH" "$PLUGIN_SLUG" -q

rm -rf "$TMP_DIR"

echo "📦  ZIP generado : $ZIP_PATH"
echo "    Tamaño       : $(du -sh "$ZIP_PATH" | cut -f1)"
echo ""
echo "✅  Release $NEW_VERSION lista."
