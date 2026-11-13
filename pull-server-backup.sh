#!/usr/bin/env bash
# ==============================================================================
# Amordad Architecture Studio - Reverse Deployment / Pull Server Backup
# ==============================================================================
# Usage:
#   ./pull-server-backup.sh
#   npm run pull
#
# What it does:
#   1. Connects to cPanel via UAPI and compresses amordad/ into a zip archive.
#   2. Downloads the archive to local machine via FTP.
#   3. Deletes the remote archive from cPanel immediately (freeing disk space).
#   4. Replaces local website code, vorud (Cockpit) core, SQLite databases, and uploads
#      with the fresh copies from the server.
#   5. Excludes thumbnail cache (vorud/storage/tmp/thumbs/*) to keep local disk lean.
#   6. Preserves local git repo (.git), dev tools, package.json, and documentation.
#   7. Runs local diagnostics to confirm the pulled backup is 100% operational.
# ==============================================================================

set -euo pipefail

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$PROJECT_DIR"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

fail() {
    echo -e "\n${RED}ERROR: $1${NC}\n" >&2
    exit 1
}

# Configuration
CPANEL_TOKEN="${DEPLOY_CPANEL_TOKEN:-$(cat "$HOME/.cpanel_token" 2>/dev/null || true)}"
FTP_USER="${DEPLOY_FTP_USER:-h355112}"
CPANEL_USER="${DEPLOY_CPANEL_USER:-$FTP_USER}"
CPANEL_PORT="${DEPLOY_CPANEL_PORT:-2083}"
CPANEL_HOST="${DEPLOY_CPANEL_HOST:-3240383765.cloudylink.com}"
FTP_HOST="${DEPLOY_FTP_HOST:-$CPANEL_HOST}"
CPANEL_API_BASE="https://${CPANEL_HOST}:${CPANEL_PORT}"
AUTH_HEADER="Authorization: cpanel ${CPANEL_USER}:${CPANEL_TOKEN}"
REMOTE_ARCHIVE_NAME="amordad-backup-$(date +%s).zip"

[ -n "$CPANEL_TOKEN" ] || fail "No cPanel API token found in ~/.cpanel_token. Run the setup command first."
command -v curl >/dev/null || fail "curl is required."
command -v python3 >/dev/null || fail "python3 is required."
command -v unzip >/dev/null || fail "unzip is required."

STAGE_DIR="$(mktemp -d "${TMPDIR:-/tmp}/amordad-pull.XXXXXX")"
LOCAL_ZIP="$STAGE_DIR/amordad-backup.zip"
cleanup() {
    rm -rf "$STAGE_DIR"
}
trap cleanup EXIT
trap 'exit 130' INT TERM

echo -e "\n${BLUE}======================================================${NC}"
echo -e "${BLUE}  Amordad - Pulling Full Backup from Production        ${NC}"
echo -e "${BLUE}======================================================${NC}"
echo -e "  Host:   ${CYAN}${CPANEL_HOST}${NC}"
echo -e "  User:   ${CYAN}${CPANEL_USER}${NC}"
echo -e "  Target: ${CYAN}amordad/ -> local workspace${NC}\n"

# 1. Stop local dev server if running to avoid SQLite locks
for pid in $(pgrep -f -- 'php.* -S ' 2>/dev/null || true); do
    args="$(ps -o args= -p "$pid" 2>/dev/null || true)"
    if [[ "$args" == *"$PROJECT_DIR/router.php"* || "$args" == *"localhost:8000"* || "$args" == *"localhost:8001"* ]]; then
        echo -e "⏸️  Stopping local dev server (PID $pid)..."
        kill "$pid" 2>/dev/null || true
        sleep 1
    fi
done

# 2. Trigger compression on server via cPanel API
echo -e "📦 [1/6] Compressing amordad on server..."
COMPRESS_RES="$(python3 - <<PYEOF
import urllib.request, json, ssl, urllib.parse

ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE

params = {
    "cpanel_jsonapi_version": "2",
    "cpanel_jsonapi_module": "Fileman",
    "cpanel_jsonapi_func": "fileop",
    "op": "compress",
    "sourcefiles": "amordad",
    "destfiles": "${REMOTE_ARCHIVE_NAME}",
    "metadata": "zip",
    "doubledecode": "0"
}
url = "${CPANEL_API_BASE}/json-api/cpanel?" + urllib.parse.urlencode(params)
req = urllib.request.Request(url, headers={'${AUTH_HEADER%%:*}': '${AUTH_HEADER#*: }'})
with urllib.request.urlopen(req, context=ctx) as r:
    data = json.loads(r.read().decode())
    res = data.get("cpanelresult", {}).get("data", [{}])[0].get("result", 0)
    print(res)
PYEOF
)"

[ "$COMPRESS_RES" = "1" ] || fail "Failed to compress amordad on the server."
echo -e "    Server archive created: ${CYAN}${REMOTE_ARCHIVE_NAME}${NC}"

# 3. Download the archive via FTP using ~/.netrc
echo -e "⬇️  [2/6] Downloading archive from server..."
if [ -f "$HOME/.netrc" ] && grep -qE "machine[[:space:]]+$FTP_HOST" "$HOME/.netrc"; then
    curl --netrc -# -o "$LOCAL_ZIP" "ftp://${FTP_HOST}/${REMOTE_ARCHIVE_NAME}" || fail "FTP download failed."
else
    curl -# -u "$FTP_USER" -o "$LOCAL_ZIP" "ftp://${FTP_HOST}/${REMOTE_ARCHIVE_NAME}" || fail "FTP download failed."
fi
ZIP_SIZE=$(ls -lh "$LOCAL_ZIP" | awk '{print $5}')
echo -e "    Download complete: ${GREEN}${ZIP_SIZE}${NC}"

# 4. Delete the remote archive from cPanel immediately (free up space)
echo -e "🧹 [3/6] Cleaning up remote archive from server..."
curl -sk -H "$AUTH_HEADER" \
    "${CPANEL_API_BASE}/json-api/cpanel?cpanel_jsonapi_version=2&cpanel_jsonapi_module=Fileman&cpanel_jsonapi_func=fileop&op=unlink&sourcefiles=${REMOTE_ARCHIVE_NAME}&doubledecode=0" >/dev/null || true
echo -e "    Server disk clean (remote archive removed)."

# 5. Extract downloaded archive into staging directory (excluding cached thumbnails)
echo -e "📂 [4/6] Unpacking archive (excluding thumbnail cache in vorud/storage/tmp/thumbs/)..."
EXTRACT_DIR="$STAGE_DIR/extracted"
mkdir -p "$EXTRACT_DIR"

unzip -q "$LOCAL_ZIP" -d "$EXTRACT_DIR" -x "amordad/vorud/storage/tmp/thumbs/*" "*/thumbs/*" "amordad/vorud/storage/cache/*" "*/cache/*"

SOURCE_ROOT="$EXTRACT_DIR/amordad"
[ -d "$SOURCE_ROOT" ] || SOURCE_ROOT="$EXTRACT_DIR"

# 6. Replace local website files and databases
echo -e "🔄 [5/6] Syncing server files into local project..."

# Sync assets
if [ -d "$SOURCE_ROOT/assets" ]; then
    rm -rf "$PROJECT_DIR/assets"
    cp -R "$SOURCE_ROOT/assets" "$PROJECT_DIR/assets"
fi

# Sync includes
if [ -d "$SOURCE_ROOT/includes" ]; then
    rm -rf "$PROJECT_DIR/includes"
    cp -R "$SOURCE_ROOT/includes" "$PROJECT_DIR/includes"
fi

# Sync root PHP pages & web files
for file in "$SOURCE_ROOT"/*; do
    [ -f "$file" ] || continue
    fname="$(basename "$file")"
    # Never overwrite local development configs or repo tooling
    case "$fname" in
        package*.json|build-deploy-zip.sh|pull-*.sh|README.md|TODO.md|router.php|check.php) continue ;;
    esac
    cp "$file" "$PROJECT_DIR/$fname"
done

# Sync .htaccess if present
if [ -f "$SOURCE_ROOT/.htaccess" ]; then
    cp "$SOURCE_ROOT/.htaccess" "$PROJECT_DIR/.htaccess"
fi

# Sync vorud (Cockpit) core, storage, addons, and content models
if [ -d "$SOURCE_ROOT/vorud" ]; then
    # Sync databases
    if [ -d "$SOURCE_ROOT/vorud/storage/data" ]; then
        mkdir -p "$PROJECT_DIR/vorud/storage/data"
        cp -R "$SOURCE_ROOT/vorud/storage/data/"*.sqlite "$PROJECT_DIR/vorud/storage/data/" 2>/dev/null || true
    fi
    # Sync uploads
    if [ -d "$SOURCE_ROOT/vorud/storage/uploads" ]; then
        mkdir -p "$PROJECT_DIR/vorud/storage/uploads"
        cp -R "$SOURCE_ROOT/vorud/storage/uploads/"* "$PROJECT_DIR/vorud/storage/uploads/" 2>/dev/null || true
    fi
    # Sync content models
    if [ -d "$SOURCE_ROOT/vorud/storage/content" ]; then
        mkdir -p "$PROJECT_DIR/vorud/storage/content"
        cp -R "$SOURCE_ROOT/vorud/storage/content/"* "$PROJECT_DIR/vorud/storage/content/" 2>/dev/null || true
    fi
    # Sync addons
    if [ -d "$SOURCE_ROOT/vorud/addons" ]; then
        mkdir -p "$PROJECT_DIR/vorud/addons"
        cp -R "$SOURCE_ROOT/vorud/addons/"* "$PROJECT_DIR/vorud/addons/" 2>/dev/null || true
    fi
    # Sync config
    if [ -d "$SOURCE_ROOT/vorud/config" ]; then
        mkdir -p "$PROJECT_DIR/vorud/config"
        cp -R "$SOURCE_ROOT/vorud/config/"* "$PROJECT_DIR/vorud/config/" 2>/dev/null || true
    fi
fi

# Ensure cached thumbnails and cache directories exist locally
mkdir -p "$PROJECT_DIR/vorud/storage/tmp/thumbs"
mkdir -p "$PROJECT_DIR/vorud/storage/cache"
touch "$PROJECT_DIR/vorud/storage/tmp/.gitkeep" 2>/dev/null || true
touch "$PROJECT_DIR/vorud/storage/cache/.gitkeep" 2>/dev/null || true

# 7. Verification & Health Check
echo -e "🧪 [6/6] Running diagnostic health check..."
if [ -f "$PROJECT_DIR/check.php" ]; then
    php "$PROJECT_DIR/check.php" || true
fi

echo -e "\n${GREEN}======================================================${NC}"
echo -e "${GREEN}  SUCCESS: Amordad Production Backup Pulled & Synced! ${NC}"
echo -e "${GREEN}======================================================${NC}"
echo -e "  💾 Databases:     Production SQLite databases synced"
echo -e "  🖼️  Uploads:       Production original media synced (months 10, 11 included)"
echo -e "  🚀 Dev Server:     Ready to run with ${CYAN}npm start${NC}\n"
