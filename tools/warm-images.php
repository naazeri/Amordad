<?php
/**
 * Amordad Architecture Studio - Image Variant Warm-up
 *
 * Pre-generates WebP variants in vorud/storage/uploads/_v/ for every active image asset,
 * so first visitors never wait for /img/{id}/{width} to generate them.
 *
 * Options:
 *   --prune    Remove existing variant files of archived / inactive assets from uploads/_v/
 *
 * Usage:
 *   php tools/warm-images.php [--prune]
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/includes/helpers.php';
require_once dirname(__DIR__) . '/includes/images.php';

$app = cockpit_app();
if (!$app) {
    fwrite(STDERR, "Error: Cockpit CMS is not available.\n");
    exit(1);
}

$prune = in_array('--prune', $argv, true);

// Collect asset IDs from active published content only
$activeAssetIds = [];
$collect = function($data) use (&$collect, &$activeAssetIds) {
    if (!is_array($data)) return;
    if (isset($data['_id'], $data['path'], $data['mime'], $data['type'])) {
        $activeAssetIds[$data['_id']] = true;
    } else {
        foreach ($data as $val) {
            $collect($val);
        }
    }
};

// 1. Published projects
foreach ($app->dataStorage->find('content/collections/projects', ['filter' => ['_state' => 1]])->toArray() as $doc) {
    $collect($doc);
}

// 2. Published categories
foreach ($app->dataStorage->find('content/collections/categories', ['filter' => ['_state' => 1]])->toArray() as $doc) {
    $collect($doc);
}

// 3. Singletons
$collect($app->dataStorage->findOne('content/singletons', ['_model' => 'siteSettings']));
$collect($app->dataStorage->findOne('content/singletons', ['_model' => 'homePage']));

$stats = [
    'assets'           => 0,
    'skipped'          => 0,
    'skipped_archived' => 0,
    'created'          => 0,
    'present'          => 0,
    'failed'           => 0,
    'pruned'           => 0,
    'bytes'            => 0,
    'original_bytes'   => 0,
];
$failures = [];
$started = microtime(true);

// Prune variants of archived / inactive assets if requested
if ($prune) {
    $vDir = img_uploads_dir() . '/_v';
    if (is_dir($vDir)) {
        foreach (scandir($vDir) as $f) {
            if ($f === '.' || $f === '..' || !preg_match('/^([a-z0-9]{16,40})-/', $f, $m)) {
                continue;
            }
            $assetId = $m[1];
            if (!isset($activeAssetIds[$assetId])) {
                @unlink("{$vDir}/{$f}");
                $stats['pruned']++;
            }
        }
    }
}

foreach ($app->dataStorage->find('assets', ['filter' => ['type' => 'image'], 'sort' => ['_created' => 1]])->toArray() as $asset) {
    $stats['assets']++;

    // Skip assets that do not belong to active content
    if (!isset($activeAssetIds[$asset['_id']])) {
        $stats['skipped_archived']++;
        continue;
    }

    if (!img_is_resizable($asset)) {
        $stats['skipped']++;
        continue;
    }

    // Auto-heal master image if it exceeds IMG_MAX_DIMENSION (1920px)
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
            $failures[] = "{$asset['_id']} {$asset['path']} @ {$w}px";
            continue;
        }

        $had ? $stats['present']++ : $stats['created']++;
        $stats['bytes'] += (int) filesize($file);
    }

    $stats['original_bytes'] += (int) ($asset['size'] ?? 0);
}

$mb = fn(int $bytes) => number_format($bytes / 1048576, 2) . ' MB';

printf(
    "Active Assets: %d (%d skipped archived, %d not resizable)\nVariants: %d created, %d already present, %d failed%s\nVariant files: %s (originals: %s)\nTime: %.2fs\n",
    $stats['assets'] - $stats['skipped_archived'],
    $stats['skipped_archived'],
    $stats['skipped'],
    $stats['created'],
    $stats['present'],
    $stats['failed'],
    $prune ? ", {$stats['pruned']} pruned" : "",
    $mb($stats['bytes']),
    $mb($stats['original_bytes']),
    microtime(true) - $started
);

if ($failures) {
    fwrite(STDERR, "Failed:\n  " . implode("\n  ", $failures) . "\n");
    exit(1);
}
