<?php
/**
 * Amordad Architecture Studio - Post-Deploy Automated Warmup Endpoint
 *
 * Secure single-use webhook triggered by build-deploy-zip.sh immediately
 * after files are extracted on the server.
 *
 * Security:
 * - Accepts POST only.
 * - Requires X-Deploy-Token matching vorud/storage/data/.deploy-token.
 * - Comparison is constant-time (hash_equals).
 * - Token is single-use: self-destructs (unlinked) immediately upon successful validation.
 * - Token has a 5-minute expiration window.
 * - Output is no-store and noindex.
 */

ini_set('display_errors', '0');
@set_time_limit(180);
if (function_exists('ini_set')) {
    @ini_set('memory_limit', '256M');
}

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$tokenHeader = $_SERVER['HTTP_X_DEPLOY_TOKEN'] ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
if (str_starts_with($tokenHeader, 'Bearer ')) {
    $token = substr($tokenHeader, 7);
} else {
    $token = $tokenHeader;
}
$token = trim((string) $token);

$tokenFile = __DIR__ . '/vorud/storage/data/.deploy-token';

if ($token === '' || !is_file($tokenFile)) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$tokenData = json_decode((string) @file_get_contents($tokenFile), true);
if (!is_array($tokenData) || empty($tokenData['token']) || empty($tokenData['expires'])) {
    @unlink($tokenFile);
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

// Check expiration (5-minute window)
if (time() > (int) $tokenData['expires']) {
    @unlink($tokenFile);
    http_response_code(403);
    echo json_encode(['error' => 'Token expired']);
    exit;
}

// Constant-time token comparison
if (!hash_equals((string) $tokenData['token'], $token)) {
    usleep(100000); // 100ms timing defense
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

// Single-use: delete token immediately upon validation
@unlink($tokenFile);

// Begin warmup execution
$started = microtime(true);
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/images.php';

$app = cockpit_app();
if (!$app) {
    http_response_code(500);
    echo json_encode(['error' => 'Cockpit is not available']);
    exit;
}

$stats = [
    'assets'   => 0,
    'skipped'  => 0,
    'created'  => 0,
    'present'  => 0,
    'failed'   => 0,
    'bytes'    => 0,
];

foreach ($app->dataStorage->find('assets', ['filter' => ['type' => 'image'], 'sort' => ['_created' => 1]])->toArray() as $asset) {
    if (!img_asset_is_active($asset['_id'], $app) || !img_is_resizable($asset)) {
        $stats['skipped']++;
        continue;
    }

    $stats['assets']++;

    // Auto-heal master image if needed
    $asset = img_ensure_master_optimized($asset, $app);

    $width = (int) ($asset['width'] ?? 0);
    $widths = IMG_WIDTHS;
    sort($widths);
    $widths = array_filter($widths, fn($w) => in_array($w, IMG_ALLOWED_WIDTHS, true) && ($w < $width || ($w === $width && ($asset['mime'] ?? '') !== 'image/webp')));

    foreach ($widths as $w) {
        $had = img_variant_file($asset, $w);
        $url = img_generate_variant($asset, $w);
        $file = img_variant_file($asset, $w);

        if (!$url || !$file) {
            $stats['failed']++;
            continue;
        }

        if ($had) {
            $stats['present']++;
        } else {
            $stats['created']++;
        }
        $stats['bytes'] += (int) @filesize($file);
    }
}

$elapsed = round(microtime(true) - $started, 2);

echo json_encode([
    'success'          => true,
    'active_assets'    => $stats['assets'],
    'variants_created' => $stats['created'],
    'variants_present' => $stats['present'],
    'failed'           => $stats['failed'],
    'variant_bytes'    => $stats['bytes'],
    'elapsed_sec'      => $elapsed,
]);
