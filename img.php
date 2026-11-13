<?php
/**
 * Amordad Architecture Studio - Image Variant Endpoint
 *
 * /img/{assetId}/{width} -> creates the resized WebP copy once, then redirects to the static file.
 * Subsequent visits hit the static file directly with zero PHP execution.
 */

require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/images.php';

$id = (string) ($_GET['id'] ?? '');
$w = (int) ($_GET['w'] ?? 0);

if (!preg_match('/^[a-z0-9]{16,40}$/', $id) || !in_array($w, IMG_ALLOWED_WIDTHS, true)) {
    http_response_code(404);
    exit;
}

$app = cockpit_app();
$asset = $app ? $app->dataStorage->findOne('assets', ['_id' => $id]) : null;

if (!$asset || ($asset['type'] ?? '') !== 'image') {
    http_response_code(404);
    exit;
}

if (!img_optimization_enabled() || !img_asset_is_active($id, $app)) {
    header('Cache-Control: no-cache');
    header('Location: ' . asset_url_of($asset), true, 302);
    exit;
}

$url = img_generate_variant($asset, $w) ?? asset_url_of($asset);

header('Cache-Control: no-cache');
header('Location: ' . $url, true, 302);
