<?php
/**
 * Amordad Architecture Studio - Master Image Optimizer CLI
 *
 * Scans all image assets in Cockpit CMS and downscales any master image whose
 * longest side exceeds 1920px (width for landscape, height for portrait) in-place.
 * Also corrects EXIF orientation, compresses masters to clean web standards,
 * and updates Cockpit database records and collection references.
 *
 * Options:
 *   --dry-run    Preview what would be optimized without modifying files or database
 *
 * Usage:
 *   php tools/optimize-masters.php [--dry-run]
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

$dryRun = in_array('--dry-run', $argv, true);
$started = microtime(true);

echo "=====================================================\n";
echo " Amordad - Master Image Optimizer\n";
echo " Target Max Dimension: " . IMG_MAX_DIMENSION . "px (longest side)\n";
echo " Mode: " . ($dryRun ? "DRY RUN (no files modified)" : "EXECUTE (modifying in-place)") . "\n";
echo "=====================================================\n\n";

$assets = $app->dataStorage->find('assets', [
    'filter' => ['type' => 'image'],
    'sort'   => ['_created' => 1]
])->toArray();

$totalAssets = count($assets);
$oversized = 0;
$bytesBefore = 0;
$bytesAfter = 0;
$errors = [];

$fmtKb = fn(int $b) => number_format($b / 1024, 1) . ' KB';
$fmtMb = fn(int $b) => number_format($b / (1024 * 1024), 2) . ' MB';

foreach ($assets as $asset) {
    $id = $asset['_id'] ?? 'unknown';
    $title = $asset['title'] ?? basename($asset['path'] ?? 'image');
    $path = $asset['path'] ?? '';

    if (empty($path)) {
        continue;
    }

    $filePath = img_uploads_dir() . '/' . ltrim($path, '/');
    if (!is_file($filePath)) {
        continue;
    }

    $info = @getimagesize($filePath);
    if (!$info) {
        continue;
    }

    [$w0, $h0, $type] = $info;
    $orientation = 1;
    if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $exif = @exif_read_data($filePath);
        $orientation = (int) ($exif['Orientation'] ?? 1);
        if ($orientation < 1 || $orientation > 8) {
            $orientation = 1;
        }
    }

    $dispW = $orientation >= 5 ? $h0 : $w0;
    $dispH = $orientation >= 5 ? $w0 : $h0;
    $maxDim = max($dispW, $dispH);
    $size = (int) filesize($filePath);

    if ($maxDim > IMG_MAX_DIMENSION || $orientation > 1) {
        $oversized++;
        $bytesBefore += $size;

        $scale = $maxDim > IMG_MAX_DIMENSION ? (IMG_MAX_DIMENSION / $maxDim) : 1.0;
        $targetW = max(1, (int) round($dispW * $scale));
        $targetH = max(1, (int) round($dispH * $scale));

        if ($dryRun) {
            printf(
                "  [DRY-RUN] %s | %dx%d (%s) -> Target: %dx%d | %s\n",
                $id,
                $dispW,
                $dispH,
                $fmtMb($size),
                $targetW,
                $targetH,
                $title
            );
            $bytesAfter += (int) round($size * 0.25); // estimated savings
        } else {
            $updated = img_ensure_master_optimized($asset, $app);
            $newSize = (int) ($updated['size'] ?? filesize($filePath));
            $bytesAfter += $newSize;
            $savedPct = $size > 0 ? round((1 - ($newSize / $size)) * 100, 1) : 0;

            printf(
                "  [OPTIMIZED] %s | %dx%d (%s) -> %dx%d (%s, -%s%%) | %s\n",
                $id,
                $dispW,
                $dispH,
                $fmtMb($size),
                $updated['width'] ?? $targetW,
                $updated['height'] ?? $targetH,
                $fmtMb($newSize),
                $savedPct,
                $title
            );
        }
    }
}

$elapsed = round(microtime(true) - $started, 2);
$savedBytes = $bytesBefore - $bytesAfter;
$savedPercent = $bytesBefore > 0 ? round(($savedBytes / $bytesBefore) * 100, 1) : 0;

echo "\n-----------------------------------------------------\n";
echo " Summary:\n";
echo "  Total Image Assets Scanned: {$totalAssets}\n";
echo "  Oversized Assets Identified: {$oversized}\n";

if ($dryRun) {
    echo "  Total Original Size of Oversized: " . $fmtMb($bytesBefore) . "\n";
    echo "  Estimated Space to be Reclaimed: ~" . $fmtMb($savedBytes) . " (~{$savedPercent}%)\n";
    echo "  Time: {$elapsed}s\n";
    echo "\n  Run 'php tools/optimize-masters.php' without --dry-run to apply.\n";
} else {
    echo "  Before Total Size: " . $fmtMb($bytesBefore) . "\n";
    echo "  After Total Size:  " . $fmtMb($bytesAfter) . "\n";
    echo "  Disk Space Reclaimed: " . $fmtMb($savedBytes) . " ({$savedPercent}% reduction)\n";
    echo "  Time: {$elapsed}s\n";
}
echo "=====================================================\n";
