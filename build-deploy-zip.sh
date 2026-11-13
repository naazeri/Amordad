#!/usr/bin/env bash

# ==============================================================================
# Amordad Architecture Studio - Production Deployment Packager for cPanel / Linux
# ==============================================================================
# Packages the project into a clean ZIP for upload to cPanel and automatically
# uploads, replaces existing server files, extracts, and sets permissions via cPanel API.
#
# By default the package contains CODE + DATA: the local SQLite databases and
# uploads are included, so extracting it REPLACES production content, admin
# users and passwords with your local copy.
# Use --code-only to leave the server's databases and uploads untouched.
#
# Usage:
#   ./build-deploy-zip.sh                       # full deploy: code + databases + uploads
#   ./build-deploy-zip.sh --code-only           # code only (preserves server data)
#   ./build-deploy-zip.sh --no-upload           # build zip locally, skip upload
#   ./build-deploy-zip.sh --domain https://amordad.backends.ir
# ==============================================================================

set -euo pipefail

# Colors for terminal output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Determine project root directory
PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$PROJECT_DIR"

OUTPUT_NAME="amordad-deploy.zip"
OUTPUT_PATH="$PROJECT_DIR/$OUTPUT_NAME"
PROD_DOMAIN="https://amordad.backends.ir"
WITH_DATA=true
UPLOAD=1
AUTO_EXTRACT=1
FTP_USER="${DEPLOY_FTP_USER:-h355112}"
FTP_TARGET="${DEPLOY_FTP_TARGET:-ftp://3240383765.cloudylink.com/amordad/}"
CPANEL_TOKEN="${DEPLOY_CPANEL_TOKEN:-$(cat "$HOME/.cpanel_token" 2>/dev/null || true)}"

while [ $# -gt 0 ]; do
    case "$1" in
        --with-data) WITH_DATA=true ;; # the default; kept for older commands
        --code-only|--without-data|--no-data) WITH_DATA=false ;;
        --no-upload|--skip-upload|--build-only) UPLOAD=0 ;;
        --upload) UPLOAD=1 ;;
        --no-extract|--skip-extract) AUTO_EXTRACT=0 ;;
        --ftp-user) [ $# -ge 2 ] || fail "--ftp-user needs a username"; FTP_USER="$2"; shift ;;
        --ftp-user=*) FTP_USER="${1#--ftp-user=}" ;;
        --ftp-target) [ $# -ge 2 ] || fail "--ftp-target needs a URL"; FTP_TARGET="$2"; shift ;;
        --ftp-target=*) FTP_TARGET="${1#--ftp-target=}" ;;
        --cpanel-token) [ $# -ge 2 ] || fail "--cpanel-token needs a token"; CPANEL_TOKEN="$2"; shift ;;
        --cpanel-token=*) CPANEL_TOKEN="${1#--cpanel-token=}" ;;
        --domain) shift; PROD_DOMAIN="${1:?--domain needs a URL}" ;;
        --domain=*) PROD_DOMAIN="${1#--domain=}" ;;
        -h|--help) sed -n '3,21p' "$0"; exit 0 ;;
        http://*|https://*) PROD_DOMAIN="$1" ;; # backward compatible positional domain
        *) echo -e "${RED}Unknown option: $1${NC}"; exit 1 ;;
    esac
    shift
done

fail() {
    echo -e "${RED}ERROR: $1${NC}"
    exit 1
}

PROD_DOMAIN="${PROD_DOMAIN%/}"
[[ "$PROD_DOMAIN" =~ ^https?://[^/[:space:]]+$ ]] || fail "--domain must look like https://example.com (got '${PROD_DOMAIN}')"

in_zip() {
    # Exact path match against the archive listing
    unzip -Z1 "$OUTPUT_PATH" | grep -xF "$1" >/dev/null
}

echo -e "\n${BLUE}======================================================${NC}"
echo -e "${BLUE}  Packaging Amordad Cockpit for cPanel Deployment     ${NC}"
if $WITH_DATA; then
    echo -e "${YELLOW}  Mode: CODE + DATA (replaces production content)     ${NC}"
    echo -e "${YELLOW}  Use --code-only to keep the server's data          ${NC}"
else
    echo -e "${BLUE}  Mode: CODE ONLY (production content is untouched)   ${NC}"
fi
echo -e "${BLUE}======================================================${NC}\n"

# 1. Clean macOS artifacts
echo -e "🧹 [1/6] Cleaning macOS system files (.DS_Store, ._*)..."
find . -name ".DS_Store" -delete 2>/dev/null || true
find . -name "._*" -delete 2>/dev/null || true

# 2. Refresh models cache; with data, flush SQLite WAL into the database files
echo -e "💾 [2/6] Refreshing models cache..."
php -r '
require_once "vorud/bootstrap.php";
$app = Cockpit::instance();
$app->helper("content.model")->cache();
' || fail "Could not refresh the Cockpit models cache."

# Data mode: snapshot each database with SQLite's online backup API. This gives
# a consistent copy (including changes still in the -wal file) even while the
# local dev server is running, and never touches the live database files.
STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT

if $WITH_DATA; then
    echo -e "   Taking consistent snapshots of the SQLite databases..."
    mkdir -p "$STAGE/vorud/storage/data"
    php -r '
$stage = $argv[1];
foreach (glob("vorud/storage/data/*.sqlite") as $dbFile) {
    if (str_starts_with(basename($dbFile), "test.")) continue;
    $target = "{$stage}/vorud/storage/data/" . basename($dbFile);
    $src = new SQLite3($dbFile); // read-write open: a read-only WAL database needs an existing -shm file
    $src->busyTimeout(5000);
    $dst = new SQLite3($target);
    if (!$src->backup($dst)) { fwrite(STDERR, "Backup failed for {$dbFile}\n"); exit(1); }
    $ok = $dst->querySingle("PRAGMA integrity_check;");
    $dst->close(); $src->close();
    @unlink("{$target}-wal"); @unlink("{$target}-shm");
    if ($ok !== "ok") { fwrite(STDERR, "Integrity check failed for {$dbFile}: {$ok}\n"); exit(1); }
}
' "$STAGE" || fail "Could not snapshot the SQLite databases."
fi

# 3. Clean temporary sessions, thumbnail cache, and Cockpit cache
echo -e "🧹 [3/6] Cleaning local sessions, thumbnails, and Cockpit cache..."
rm -f vorud/storage/tmp/sess_* 2>/dev/null || true
rm -rf vorud/storage/tmp/thumbs/* 2>/dev/null || true
if [ -d "vorud/storage/cache" ]; then
    find vorud/storage/cache -type f ! -name ".gitkeep" -delete 2>/dev/null || true
fi

# Ensure all storage directories exist with .gitkeep placeholders
mkdir -p vorud/storage/{cache,data,tmp,uploads,content,logs}
touch vorud/storage/cache/.gitkeep 2>/dev/null || true
touch vorud/storage/tmp/.gitkeep 2>/dev/null || true
touch vorud/storage/uploads/.gitkeep 2>/dev/null || true
touch vorud/storage/logs/.gitkeep 2>/dev/null || true

# 4. Align production domain in robots.txt
echo -e "⚙️  [4/6] Setting production domain in robots.txt (${CYAN}${PROD_DOMAIN}${NC})..."
if [ -f "robots.txt" ]; then
    sed -i.bak -E "s|^Sitemap:.*$|Sitemap: ${PROD_DOMAIN}/sitemap.xml|" robots.txt
    rm -f robots.txt.bak
fi

# Remove old zip if present
rm -f "$OUTPUT_PATH"

# 5. Create ZIP archive
echo -e "📦 [5/6] Building production archive: ${YELLOW}${OUTPUT_NAME}${NC}..."

EXCLUDES=(
    "*.git*"
    "*.DS_Store*"
    "*__MACOSX*"
    "*.zip"
    "*.sh"
    "*.local_bak"
    "*.bak"
    "package*.json"
    "node_modules*"
    "graphify-out*"
    ".graphify*"
    ".agents*"
    ".claude*"
    ".vscode*"
    ".idea*"
    "reference*"
    "tools/*"
    "docs/*"
    "TODO.md"
    "README.md"
    "check.php"
    "router.php"
    "vorud/install/*"
    "vorud/*.md"
    "vorud/.env.example"
    "vorud/storage/tmp/sess_*"
    "vorud/storage/tmp/thumbs/*"
    "vorud/storage/cache/*"
    "vorud/storage/logs/*"
    "vorud/storage/uploads/_v/*"
    "vorud/storage/data/.sec-key"
    "vorud/storage/data/*.sqlite"
    "vorud/storage/data/*.sqlite-shm"
    "vorud/storage/data/*.sqlite-wal"
    "vorud/storage/data/*.sqlite-journal"
)

if ! $WITH_DATA; then
    EXCLUDES+=("vorud/storage/uploads/2*")
fi

ZIP_ARGS=()
for pattern in "${EXCLUDES[@]}"; do
    ZIP_ARGS+=(-x "$pattern")
done

zip -r -q "$OUTPUT_PATH" . "${ZIP_ARGS[@]}"

# Data mode: add the database snapshots (never the live files)
if $WITH_DATA; then
    (cd "$STAGE" && zip -q "$OUTPUT_PATH" vorud/storage/data/*.sqlite)
fi

# Single-use post-deploy token (expires in 5 minutes, self-destructs immediately on use)
WARMUP_TOKEN="$(openssl rand -hex 32 2>/dev/null || php -r 'echo bin2hex(random_bytes(32));')"
WARMUP_NOW="$(date +%s 2>/dev/null || php -r 'echo time();')"
WARMUP_EXPIRES=$((WARMUP_NOW + 300))
mkdir -p "$STAGE/vorud/storage/data"
echo "{\"token\":\"${WARMUP_TOKEN}\",\"expires\":${WARMUP_EXPIRES}}" > "$STAGE/vorud/storage/data/.deploy-token"
(cd "$STAGE" && zip -q "$OUTPUT_PATH" vorud/storage/data/.deploy-token)

# 6. Verification
echo -e "🔍 [6/6] Verifying package integrity..."

for required in ".htaccess" "index.php" "thumb.php" "img.php" "deploy-warmup.php" "includes/images.php" \
                 "vorud/index.php" "vorud/.htaccess" "vorud/config/config.php" "vorud/config/bootstrap.php" \
                 "vorud/config/theme.css" "vorud/storage/data/.htaccess" "vorud/storage/data/.deploy-token" \
                 "vorud/addons/AiTranslate/bootstrap.php" "vorud/addons/Hardening/bootstrap.php" \
                 "vorud/addons/ModelSync/bootstrap.php" "vorud/addons/AdminRtl/bootstrap.php"; do
    in_zip "$required" || fail "$required is missing from the archive!"
done

if [ ! -f "vorud/.env" ]; then
    echo -e "${YELLOW}WARNING: vorud/.env not found; the server will generate its own sec-key.${NC}"
fi

for forbidden in "check.php" "TODO.md" "README.md" "router.php" "vorud/storage/data/.sec-key" "package.json"; do
    in_zip "$forbidden" && fail "$forbidden must not be in the archive!"
done

if unzip -Z1 "$OUTPUT_PATH" | grep -E "^\.(claude|vscode|idea)/" >/dev/null; then
    fail "Editor or AI tooling folders were found in the archive!"
fi

if unzip -Z1 "$OUTPUT_PATH" | grep -E "(\.sqlite-shm|\.sqlite-wal)$" >/dev/null; then
    fail "SQLite -shm or -wal files were found in the archive!"
fi

if unzip -Z1 "$OUTPUT_PATH" | grep -E "(\.git/|\.DS_Store|__MACOSX)" >/dev/null; then
    fail "Git, macOS or temporary files were found in the archive!"
fi

if $WITH_DATA; then
    in_zip "vorud/storage/data/content.sqlite" || fail "content.sqlite database is missing from the archive!"
    in_zip "vorud/storage/data/system.sqlite" || fail "system.sqlite database is missing from the archive!"
else
    if unzip -Z1 "$OUTPUT_PATH" | grep -E "\.sqlite$|^vorud/storage/uploads/2" >/dev/null; then
        fail "Code-only package contains databases or uploads!"
    fi
fi

ZIP_SIZE=$(ls -lh "$OUTPUT_PATH" | awk '{print $5}')
TOTAL_FILES=$(unzip -Z1 "$OUTPUT_PATH" | wc -l | tr -d ' ')

echo -e "\n${GREEN}======================================================${NC}"
echo -e "${GREEN}  SUCCESS: Deployment package ready!                  ${NC}"
echo -e "${GREEN}======================================================${NC}"
echo -e "📁 Output File:   ${YELLOW}${OUTPUT_PATH}${NC}"
echo -e "📊 Archive Size:  ${GREEN}${ZIP_SIZE}${NC}"
echo -e "📄 Total Files:   ${GREEN}${TOTAL_FILES}${NC}"
echo -e "🌐 Domain Set:    ${CYAN}${PROD_DOMAIN}${NC}\n"

# 7. Upload to FTP server (enabled by default)
if [ "$UPLOAD" -eq 1 ]; then
    echo -e "${BLUE}======================================================${NC}"
    echo -e "${BLUE}  Uploading ${OUTPUT_NAME} to FTP Server...           ${NC}"
    echo -e "${BLUE}======================================================${NC}"
    echo -e "  Target: ${CYAN}${FTP_TARGET}${NC}"
    echo -e "  User:   ${CYAN}${FTP_USER}${NC}\n"

    FTP_HOST="$(echo "$FTP_TARGET" | sed -E 's|^[a-zA-Z]+://([^/:]+).*|\1|')"
    if [ -f "$HOME/.netrc" ] && grep -qE "machine[[:space:]]+$FTP_HOST" "$HOME/.netrc"; then
        curl --netrc -T "$OUTPUT_PATH" "$FTP_TARGET" || fail "FTP upload failed."
    else
        curl -T "$OUTPUT_PATH" -u "$FTP_USER" "$FTP_TARGET" || fail "FTP upload failed."
    fi

    echo -e "\n${GREEN}  SUCCESS: Upload complete!${NC}\n"

    # 8. Automatically clean old files, extract archive, and remove zip via cPanel API
    if [ "$AUTO_EXTRACT" -eq 1 ] && [ -n "$CPANEL_TOKEN" ]; then
        echo -e "${BLUE}======================================================${NC}"
        echo -e "${BLUE}  Automating cPanel Extraction & Clean-up...          ${NC}"
        echo -e "${BLUE}======================================================${NC}"

        CPANEL_USER="${DEPLOY_CPANEL_USER:-$FTP_USER}"
        CPANEL_PORT="${DEPLOY_CPANEL_PORT:-2083}"
        CPANEL_HOST="${DEPLOY_CPANEL_HOST:-$FTP_HOST}"
        CPANEL_HOME="/home/$CPANEL_USER"
        CPANEL_SITE_DIR="$CPANEL_HOME/amordad"
        CPANEL_API_BASE="https://${CPANEL_HOST}:${CPANEL_PORT}"
        AUTH_HEADER="Authorization: cpanel ${CPANEL_USER}:${CPANEL_TOKEN}"

        echo -e "🧹 [1/3] Removing old website files in amordad/ (permanent delete)..."
        python3 - <<PYEOF
import urllib.request, json, ssl, urllib.parse

ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE

headers = {'Authorization': 'cpanel ${CPANEL_USER}:${CPANEL_TOKEN}'}
list_url = '${CPANEL_API_BASE}/execute/Fileman/list_files?dir=amordad'
req = urllib.request.Request(list_url, headers=headers)
try:
    with urllib.request.urlopen(req, context=ctx) as resp:
        res = json.loads(resp.read().decode())
except Exception as e:
    print('    Warning: failed to list files:', e)
    res = {}

files_to_delete = []
for item in res.get('data', []):
    name = item.get('file', '')
    if not name.startswith('.') and name != '${OUTPUT_NAME}':
        files_to_delete.append('amordad/' + name)

if files_to_delete:
    print(f'    Removing {len(files_to_delete)} old items: ' + ', '.join(f.replace('amordad/', '') for f in files_to_delete))
    for f in files_to_delete:
        del_url = '${CPANEL_API_BASE}/json-api/cpanel?cpanel_jsonapi_version=2&cpanel_jsonapi_module=Fileman&cpanel_jsonapi_func=fileop&op=unlink&sourcefiles=' + urllib.parse.quote(f) + '&doubledecode=0'
        try:
            dreq = urllib.request.Request(del_url, headers=headers)
            urllib.request.urlopen(dreq, context=ctx)
        except Exception as e:
            print(f'    Warning: could not delete {f}: {e}')
    print('    Old files cleaned up.')
else:
    print('    No old files to clean.')
PYEOF

        echo -e "📦 [2/3] Extracting ${OUTPUT_NAME} into amordad/..."
        EXTRACT_RES="$(curl -sk -H "$AUTH_HEADER" \
            "${CPANEL_API_BASE}/json-api/cpanel?cpanel_jsonapi_version=2&cpanel_jsonapi_module=Fileman&cpanel_jsonapi_func=fileop&op=extract&sourcefiles=amordad/${OUTPUT_NAME}&destfiles=${CPANEL_SITE_DIR}&doubledecode=0")"

        if ! grep -qE '"result":[[:space:]]*1' <<< "$EXTRACT_RES"; then
            fail "cPanel extraction failed: $EXTRACT_RES"
        fi
        echo -e "    Extracted successfully."

        # Make storage folder writable
        curl -sk -H "$AUTH_HEADER" \
            "${CPANEL_API_BASE}/json-api/cpanel?cpanel_jsonapi_version=2&cpanel_jsonapi_module=Fileman&cpanel_jsonapi_func=fileop&op=chmod&sourcefiles=amordad/vorud/storage&metadata=0775&doubledecode=0" >/dev/null || true

        echo -e "🗑️  [3/3] Removing ${OUTPUT_NAME} from server..."
        curl -sk -H "$AUTH_HEADER" \
            "${CPANEL_API_BASE}/json-api/cpanel?cpanel_jsonapi_version=2&cpanel_jsonapi_module=Fileman&cpanel_jsonapi_func=fileop&op=unlink&sourcefiles=amordad/${OUTPUT_NAME}&doubledecode=0" >/dev/null || true
        echo -e "    Archive removed."

        echo -e "⚡ [4/4] Triggering automated post-deploy cache warmup..."
        WARMUP_RESP="$(curl -sk -m 90 -X POST "${PROD_DOMAIN}/deploy-warmup.php" \
            -H "X-Deploy-Token: ${WARMUP_TOKEN}" 2>/dev/null || true)"

        if [[ "$WARMUP_RESP" =~ \"success\":true ]]; then
            CREATED_CNT="$(sed -nE 's/.*"variants_created":([0-9]+).*/\1/p' <<< "$WARMUP_RESP" || echo 0)"
            PRESENT_CNT="$(sed -nE 's/.*"variants_present":([0-9]+).*/\1/p' <<< "$WARMUP_RESP" || echo 0)"
            ELAPSED_SEC="$(sed -nE 's/.*"elapsed_sec":([0-9.]+).*/\1/p' <<< "$WARMUP_RESP" || echo 0)"
            echo -e "    ${GREEN}Cache warmup complete:${NC} ${CREATED_CNT} created, ${PRESENT_CNT} existing (${ELAPSED_SEC}s)."
        else
            echo -e "    ${YELLOW}Notice: Automated warmup response: ${WARMUP_RESP:-No response}${NC}"
        fi

        echo -e "\n${GREEN}======================================================${NC}"
        echo -e "${GREEN}  SUCCESS: Fully Automated Deployment Complete!       ${NC}"
        echo -e "${GREEN}======================================================${NC}"
        echo -e "  🌐 Site URL:       ${CYAN}${PROD_DOMAIN}${NC}"
        echo -e "  🚀 Status:         Online & active"
        echo -e "  🧹 Server Clean:   Archive & old files removed (0 disk waste)\n"
        exit 0
    fi
else
    echo -e "${YELLOW}Upload skipped (--no-upload).${NC}\n"
fi

echo -e "${BLUE}Deployment Checklist for cPanel / Linux Server:${NC}"
echo -e "  1. BACK UP the server first: download ${YELLOW}vorud/storage${NC} from the site folder (or take a cPanel backup)."
echo -e "  2. Upload ${YELLOW}${OUTPUT_NAME}${NC} to the site's document root."
if $WITH_DATA; then
    echo -e "  3. ${YELLOW}Before extracting:${NC} delete ${YELLOW}vorud/storage/data/*.sqlite-wal${NC} and ${YELLOW}*.sqlite-shm${NC} on the server"
    echo -e "     (old WAL files would be replayed onto the new databases and corrupt them)."
    echo -e "  4. Right-click and Extract the archive (overwrite existing files)."
else
    echo -e "  3. (Code only: no database files to remove.)"
    echo -e "  4. Right-click and Extract the archive (overwrite existing files)."
fi
echo -e "  5. ${RED}DELETE ${OUTPUT_NAME} from the server immediately after extracting.${NC}"
echo -e "  6. Ensure ${YELLOW}vorud/storage${NC} permissions are writable (chmod 755 or 775)."
echo -e "  7. Check the PHP version for the domain in MultiPHP Manager (extracting replaces .htaccess)."
echo -e "  Diagnostics (optional): upload ${CYAN}check.php${NC} separately, run ${CYAN}php check.php${NC} in the cPanel terminal, then delete it.\n"
