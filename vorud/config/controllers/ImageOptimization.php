<?php

namespace Amordad\Controller;

use App\Controller\App;

class ImageOptimization extends App {

    protected function initialize() {
        parent::initialize();
        require_once dirname(__DIR__, 3) . '/includes/images.php';
    }

    public function index() {
        if (!$this->isAllowed('app/system/info')) {
            return $this->stop(401);
        }

        $stats = $this->collectStats();
        $enabled = img_optimization_enabled();

        return $this->render(dirname(__DIR__) . '/views/image-optimization.php', compact('stats', 'enabled'));
    }

    public function stats() {
        if (!$this->isAllowed('app/system/info')) {
            return $this->stop(401);
        }

        return [
            'success' => true,
            'enabled' => img_optimization_enabled(),
            'stats'   => $this->collectStats(),
        ];
    }

    public function toggle() {
        if (!$this->hasValidCsrfToken(false)) {
            return $this->stop(['error' => 'Security token expired. Please refresh the page.'], 412);
        }

        if (!$this->isAllowed('app/system/info')) {
            return $this->stop(401);
        }

        $enabled = (bool) $this->param('enabled', true);
        $content = $this->app->module('content');

        if ($content) {
            $data = $content->item('siteSettings') ?: [];
            $data['image_optimization'] = $enabled;
            $content->saveItem('siteSettings', $data);
        }

        return [
            'success' => true,
            'enabled' => $enabled,
            'message' => $enabled ? 'Image optimization enabled' : 'Image optimization disabled (serving original Cockpit assets)',
        ];
    }

    public function cleanCache() {
        if (!$this->hasValidCsrfToken(false)) {
            return $this->stop(['error' => 'Security token expired. Please refresh the page.'], 412);
        }

        if (!$this->isAllowed('app/system/info')) {
            return $this->stop(401);
        }

        $vDir = img_uploads_dir() . '/_v';
        $deleted = 0;
        $reclaimed = 0;

        if (is_dir($vDir)) {
            $files = glob($vDir . '/*') ?: [];
            foreach ($files as $file) {
                if (is_file($file) && basename($file) !== '.gitkeep') {
                    $reclaimed += filesize($file);
                    if (@unlink($file)) {
                        $deleted++;
                    }
                }
            }
        }

        // Also purge orientation cache
        $cacheFile = dirname(__DIR__, 3) . '/vorud/storage/cache/.img-orientation.json';
        if (is_file($cacheFile)) {
            @unlink($cacheFile);
        }

        return [
            'success'         => true,
            'deleted_files'   => $deleted,
            'reclaimed_bytes' => $reclaimed,
            'stats'           => $this->collectStats(),
        ];
    }

    public function getQueue() {
        if (!$this->isAllowed('app/system/info')) {
            return $this->stop(401);
        }

        $app = $this->app;

        $assets = $app->dataStorage->find('assets', [
            'filter' => ['type' => 'image'],
            'sort'   => ['_created' => 1],
        ])->toArray();

        $queue = [];
        foreach ($assets as $asset) {
            if (!img_asset_is_active($asset['_id'], $app)) {
                continue;
            }

            if (!img_is_resizable($asset)) {
                continue;
            }

            $width = (int) ($asset['width'] ?? 0);
            $widths = IMG_WIDTHS;
            sort($widths);
            $widths = array_values(array_filter($widths, fn($w) => in_array($w, IMG_ALLOWED_WIDTHS, true) && ($w < $width || ($w === $width && ($asset['mime'] ?? '') !== 'image/webp'))));

            if (empty($widths)) {
                continue;
            }

            $title = $asset['title'] ?? basename($asset['path'] ?? 'image');
            $queue[] = [
                'id'       => $asset['_id'],
                'title'    => $title,
                'path'     => $asset['path'] ?? '',
                'width'    => $width,
                'height'   => (int) ($asset['height'] ?? 0),
                'size'     => (int) ($asset['size'] ?? 0),
                'widths'   => $widths,
                'thumbUrl' => '/vorud/assets/thumbnail?src=' . rawurlencode($asset['_id']) . '&m=bestFit&mime=auto&w=60&h=60',
            ];
        }

        return [
            'success' => true,
            'total'   => count($queue),
            'queue'   => $queue,
        ];
    }

    public function processBatch() {
        if (!$this->hasValidCsrfToken(false)) {
            return $this->stop(['error' => 'Security token expired. Please refresh the page.'], 412);
        }

        if (!$this->isAllowed('app/system/info')) {
            return $this->stop(401);
        }

        $assetIds = (array) $this->param('asset_ids', []);
        if (empty($assetIds)) {
            return $this->stop(['error' => 'No asset IDs provided'], 400);
        }

        $app = $this->app;
        $created = 0;
        $alreadyPresent = 0;
        $failed = 0;
        $bytes = 0;
        $errors = [];

        foreach ($assetIds as $id) {
            if (!img_asset_is_active($id, $app)) {
                continue;
            }

            $asset = $app->dataStorage->findOne('assets', ['_id' => $id]);
            if (!$asset || !img_is_resizable($asset)) {
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
                    $failed++;
                    $errors[] = "Failed generating {$asset['_id']} ({$asset['title']}) at {$w}px";
                    continue;
                }

                if ($had) {
                    $alreadyPresent++;
                } else {
                    $created++;
                }
                $bytes += (int) @filesize($file);

                // Yield CPU slice to concurrent web requests
                usleep(20000); // 20ms pause per variant
            }
        }

        return [
            'success'         => true,
            'processed_count' => count($assetIds),
            'variants_created'=> $created,
            'already_present' => $alreadyPresent,
            'failed_count'    => $failed,
            'total_bytes'     => $bytes,
            'errors'          => $errors,
            'stats'           => $this->collectStats(),
        ];
    }

    protected function collectStats(): array {
        $app = $this->app;

        $originals = 0;
        $originalBytes = 0;
        foreach ($app->dataStorage->find('assets', ['filter' => ['type' => 'image']])->toArray() as $doc) {
            if (!img_asset_is_active($doc['_id'], $app)) {
                continue;
            }
            $originals++;
            $originalBytes += (int) ($doc['size'] ?? 0);
        }

        $variants = 0;
        $variantBytes = 0;
        $vDir = img_uploads_dir() . '/_v';
        if (is_dir($vDir)) {
            $files = glob($vDir . '/*') ?: [];
            foreach ($files as $f) {
                if (is_file($f) && basename($f) !== '.gitkeep') {
                    $variants++;
                    $variantBytes += filesize($f);
                }
            }
        }

        $fmt = fn(int $b) => number_format($b / (1024 * 1024), 2) . ' MB';
        $fmtKb = fn(float $b) => number_format($b / 1024, 1) . ' KB';

        $avgOriginalBytes = $originals > 0 ? ($originalBytes / $originals) : 0;
        $avgVariantBytes  = $variants > 0 ? ($variantBytes / $variants) : 0;
        $avgSavingsPercent = $avgOriginalBytes > 0 ? round((1 - ($avgVariantBytes / $avgOriginalBytes)) * 100, 1) : 0;
        $diskSavingsPercent = $originalBytes > 0 ? round((1 - ($variantBytes / $originalBytes)) * 100, 1) : 0;
        $diffBytes = $originalBytes - $variantBytes;

        return [
            'original_count'       => $originals,
            'original_bytes'       => $originalBytes,
            'original_size_human'  => $fmt($originalBytes),
            'variant_count'        => $variants,
            'variant_bytes'        => $variantBytes,
            'variant_size_human'   => $fmt($variantBytes),
            'avg_original_human'   => $fmtKb($avgOriginalBytes),
            'avg_variant_human'    => $fmtKb($avgVariantBytes),
            'avg_savings_percent'  => $avgSavingsPercent,
            'disk_savings_percent' => $diskSavingsPercent,
            'diff_human'           => $fmt(abs($diffBytes)),
            'cache_dir'            => $vDir,
        ];
    }
}
