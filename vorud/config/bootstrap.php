<?php
/**
 * Amordad site hooks for Cockpit CMS.
 *
 * Included by Cockpit on every boot (admin, API, CLI, and public site).
 */

// ── Master Image Ingestion Hook ──
// Intercept any uploaded image (via Cockpit Admin UI, API, or programmatic upload)
// and downscale it in-place to max 1920px (on its longest side) before Cockpit writes it to uploads/.
$app->on('assets.asset.upload', function (&$asset, &$meta, &$opts, &$file, &$path) {
    if (($asset['type'] ?? '') !== 'image') {
        return;
    }
    require_once dirname(__DIR__, 2) . '/includes/images.php';
    $res = img_optimize_master_file($file, IMG_MAX_DIMENSION);
    if ($res && !empty($res['scaled'])) {
        $asset['width'] = $res['width'];
        $asset['height'] = $res['height'];
        $asset['size'] = $res['size'];
        $asset['_hash'] = $res['hash'];
    }
});

// ── Image Optimization Admin Controller & Settings Menu ──
$app->on('app.admin.init', function () {
    require_once __DIR__ . '/controllers/ImageOptimization.php';

    // Explicit exact route bindings guarantee O(1) matching over any wildcard route
    foreach (['toggle', 'cleanCache', 'getQueue', 'processBatch', 'stats'] as $action) {
        $this->bind("/system/image-optimization/{$action}", function () use ($action) {
            return $this->invoke('Amordad\\Controller\\ImageOptimization', $action);
        });
    }

    $this->bindClass('Amordad\Controller\ImageOptimization', '/system/image-optimization');

    $this->on('app.settings.collect', function ($settings) {
        $settings['System'][] = [
            'icon'       => 'system:assets/icons/image.svg',
            'route'      => '/system/image-optimization',
            'label'      => 'Image Optimization',
            'permission' => 'app/system/info',
        ];
    });
});
