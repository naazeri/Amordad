<?php
/**
 * Amordad Architecture Studio - Responsive Image Optimization Pipeline
 *
 * Master images are Cockpit assets (arrays with _id, path, width, height, mime, _hash).
 * Master ceiling: max(width, height) <= 1920px.
 * Resized copies ("variants") live in vorud/storage/uploads/_v/ and are created
 * on first request by /img/{id}/{width} (img.php), ahead of time by tools/warm-images.php,
 * or post-deploy via deploy-warmup.php.
 */

const IMG_MAX_DIMENSION = 1920;
const IMG_JPEG_QUALITY = 84;
const IMG_WEBP_QUALITY = 82;
const IMG_WIDTHS = [300, 600, 800, 1200, 1600, 1920];
const IMG_ALLOWED_WIDTHS = [128, 300, 480, 600, 800, 900, 1200, 1600, 1920];
const IMG_UPLOADS_URL = '/vorud/storage/uploads';

if (!function_exists('cockpit_app')) {
    function cockpit_app() {
        static $app = null;
        if ($app === null && class_exists('Cockpit')) {
            $app = Cockpit::instance();
        }
        return $app;
    }
}

/**
 * Check whether responsive WebP image optimization is active.
 * When false, the website serves the original Cockpit assets directly.
 */
function img_optimization_enabled(?bool $reset = null): bool {
    static $enabled = null;
    if ($reset !== null) {
        $enabled = $reset;
        return $enabled;
    }
    if ($enabled !== null) {
        return $enabled;
    }
    if (function_exists('getSiteSettings')) {
        $settings = getSiteSettings();
        if (is_array($settings) && array_key_exists('image_optimization', $settings)) {
            $enabled = (bool) $settings['image_optimization'];
            return $enabled;
        }
    }
    $app = cockpit_app();
    if ($app) {
        $content = $app->module('content');
        if ($content) {
            $data = $content->item('siteSettings');
            if (is_array($data) && array_key_exists('image_optimization', $data)) {
                $enabled = (bool) $data['image_optimization'];
                return $enabled;
            }
        }
    }
    $enabled = true;
    return $enabled;
}

function img_uploads_dir(): string {
    return dirname(__DIR__) . '/vorud/storage/uploads';
}

/**
 * Checks whether an asset belongs to active published content.
 */
function img_asset_is_active(string $id, $app = null): bool {
    static $activeIds = null;
    if ($activeIds === null) {
        $app = $app ?? cockpit_app();
        if (!$app) {
            return true;
        }
        $activeIds = [];
        $collect = function($data) use (&$collect, &$activeIds) {
            if (!is_array($data)) return;
            if (isset($data['_id'], $data['path'], $data['mime'], $data['type'])) {
                $activeIds[$data['_id']] = true;
            } else {
                foreach ($data as $val) {
                    $collect($val);
                }
            }
        };

        // Published projects
        foreach ($app->dataStorage->find('content/collections/projects', ['filter' => ['_state' => 1]])->toArray() as $doc) {
            $collect($doc);
        }
        // Published categories
        foreach ($app->dataStorage->find('content/collections/categories', ['filter' => ['_state' => 1]])->toArray() as $doc) {
            $collect($doc);
        }
        // Singletons
        $collect($app->dataStorage->findOne('content/singletons', ['_model' => 'siteSettings']));
        $collect($app->dataStorage->findOne('content/singletons', ['_model' => 'homePage']));
    }
    return isset($activeIds[$id]);
}

/**
 * URL of the original file. Accepts a Cockpit asset or a path string.
 */
function asset_url_of($asset): string {
    if (is_string($asset)) {
        if ($asset === '') return '';
        if (str_starts_with($asset, 'http://') || str_starts_with($asset, 'https://')) return $asset;
        $clean = '/' . ltrim($asset, '/');
        return str_starts_with($clean, IMG_UPLOADS_URL) ? $clean : IMG_UPLOADS_URL . $clean;
    }
    if (!is_array($asset) || empty($asset['path'])) {
        return '';
    }
    return IMG_UPLOADS_URL . '/' . ltrim($asset['path'], '/');
}

/**
 * EXIF Orientation (1-8) of a JPEG asset, 1 for anything else. Cockpit stores the size of the
 * pixels as saved, so an upright phone photo can be 4032x3024 with Orientation 6.
 * EXIF is read once per asset file and remembered in vorud/storage/cache/.img-orientation.json
 * (emptied by Cockpit's "Clear cache"), so page renders do not parse every JPEG each time.
 */
function img_orientation($asset): int {
    static $map = null;
    static $pending = null;

    if (!is_array($asset) || ($asset['mime'] ?? '') !== 'image/jpeg' || empty($asset['_id']) || empty($asset['path'])
        || !function_exists('exif_read_data')) {
        return 1;
    }

    $cacheFile = dirname(__DIR__) . '/vorud/storage/cache/.img-orientation.json';
    if ($map === null) {
        $map = is_file($cacheFile) ? json_decode((string) @file_get_contents($cacheFile), true) : [];
        $map = is_array($map) ? $map : [];
    }

    $key = $asset['_id'] . '-' . substr((string) ($asset['_hash'] ?? ''), 0, 8);
    if (!isset($map[$key])) {
        $exif = @exif_read_data(img_uploads_dir() . '/' . ltrim($asset['path'], '/'));
        $orientation = (int) ($exif['Orientation'] ?? 1);
        $map[$key] = $orientation >= 1 && $orientation <= 8 ? $orientation : 1;

        if ($pending === null) {
            $pending = [];
            register_shutdown_function(function () use (&$pending, $cacheFile) {
                $saved = is_file($cacheFile) ? json_decode((string) @file_get_contents($cacheFile), true) : [];
                $tmp = $cacheFile . '.' . getmypid() . '.tmp';
                if (@file_put_contents($tmp, json_encode($pending + (is_array($saved) ? $saved : []))) === false
                    || !@rename($tmp, $cacheFile)) {
                    @unlink($tmp);
                }
            });
        }
        $pending[$key] = $map[$key];
    }
    return $map[$key];
}

/**
 * Display size [width, height]: orientations 5-8 turn the picture by 90°, so width and height swap.
 */
function asset_dims($asset): array {
    if (is_array($asset) && !empty($asset['width']) && !empty($asset['height'])) {
        $w = (int) $asset['width'];
        $h = (int) $asset['height'];
        return img_orientation($asset) >= 5 ? [$h, $w] : [$w, $h];
    }
    if (is_string($asset) && $asset !== '') {
        $info = @getimagesize(dirname(__DIR__) . '/' . ltrim($asset, '/'));
        if ($info) {
            return [$info[0], $info[1]];
        }
    }
    return [null, null];
}

function asset_alt($asset, string $fallback = ''): string {
    if (is_array($asset)) {
        $alt = trim((string) ($asset['altText'] ?? $asset['description'] ?? ''));
        if ($alt !== '' && !preg_match('/\.(jpe?g|png|gif|webp|svg)$/i', $alt)) {
            return $alt;
        }
        $title = trim((string) ($asset['title'] ?? ''));
        if ($title !== '' && !preg_match('/\.(jpe?g|png|gif|webp|svg)$/i', $title)) {
            return $title;
        }
    }
    return $fallback;
}

/**
 * Only raster formats GD can read are resized; GIF and SVG are served as-is.
 */
function img_is_resizable(array $asset): bool {
    return in_array($asset['mime'] ?? '', ['image/jpeg', 'image/png', 'image/webp'], true)
        && !empty($asset['_id']) && !empty($asset['width']);
}

function img_variant_basename(array $asset, int $w): string {
    $hash = substr((string) ($asset['_hash'] ?? ''), 0, 8) ?: 'x';
    return "{$asset['_id']}-{$hash}-{$w}";
}

/**
 * Existing variant file name for $asset at width $w, or null when not generated yet.
 */
function img_variant_file(array $asset, int $w): ?string {
    $base = img_uploads_dir() . '/_v/' . img_variant_basename($asset, $w);
    foreach (['webp', 'png', 'jpg'] as $ext) {
        if (is_file("{$base}.{$ext}")) {
            return "{$base}.{$ext}";
        }
    }
    return null;
}

function img_variant_url($asset, int $w): string {
    if (!img_optimization_enabled() || !is_array($asset) || !img_is_resizable($asset) || $w > (asset_dims($asset)[0] ?? 0)) {
        return asset_url_of($asset);
    }
    $file = img_variant_file($asset, $w);
    if ($file) {
        return IMG_UPLOADS_URL . '/_v/' . basename($file);
    }
    return '/img/' . rawurlencode($asset['_id']) . '/' . $w;
}

function img_srcset($asset, array $widths = IMG_WIDTHS): string {
    if (!img_optimization_enabled()) {
        return '';
    }
    [$w0] = asset_dims($asset);
    if (!$w0 || !is_array($asset) || !img_is_resizable($asset)) {
        return '';
    }
    $out = [];
    foreach ($widths as $w) {
        if ($w < $w0) {
            $out[] = img_variant_url($asset, $w) . " {$w}w";
        }
    }
    $widestUrl = (($asset['mime'] ?? '') !== 'image/webp' && function_exists('imagewebp') && in_array($w0, IMG_ALLOWED_WIDTHS, true))
        ? img_variant_url($asset, $w0)
        : asset_url_of($asset);

    $out[] = $widestUrl . " {$w0}w";
    return implode(', ', array_unique($out));
}

/**
 * <img> tag with width/height (no layout shift), srcset/sizes and lazy loading.
 */
function responsive_img($asset, string $alt, string $sizes, array $attrs = []): string {
    [$w, $h] = asset_dims($asset);
    $widths = $attrs['widths'] ?? IMG_WIDTHS;
    $srcWidth = $attrs['src_width'] ?? 800;
    unset($attrs['widths'], $attrs['src_width']);

    $attrs = array_merge([
        'src' => is_array($asset) ? img_variant_url($asset, $srcWidth) : asset_url_of($asset),
        'srcset' => img_srcset($asset, $widths),
        'sizes' => $sizes,
        'width' => $w,
        'height' => $h,
        'alt' => $alt,
        'loading' => 'lazy',
        'decoding' => 'async',
    ], $attrs);

    if ($attrs['srcset'] === '') {
        unset($attrs['sizes']);
    }

    $html = '<img';
    foreach ($attrs as $k => $v) {
        if ($k !== 'alt' && ($v === null || $v === false || $v === '')) {
            continue;
        }
        $html .= ' ' . $k . '="' . htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8') . '"';
    }
    return $html . '>';
}

/**
 * Turn decoded JPEG pixels upright for EXIF orientation 2-8: rotate first, then mirror.
 */
function img_apply_orientation(GdImage $im, int $orientation): GdImage {
    $angle = match ($orientation) {
        3 => 180,
        5, 6 => 270,
        7, 8 => 90,
        default => 0,
    };
    if ($angle) {
        $rotated = imagerotate($im, $angle, 0);
        if (!$rotated) {
            return $im;
        }
        imagedestroy($im);
        $im = $rotated;
    }
    if (in_array($orientation, [2, 5, 7], true)) {
        imageflip($im, IMG_FLIP_HORIZONTAL);
    } elseif ($orientation === 4) {
        imageflip($im, IMG_FLIP_VERTICAL);
    }
    return $im;
}

/**
 * Create the variant file for $asset at width $w. Returns its public URL, or null on failure.
 */
function img_generate_variant(array $asset, int $w): ?string {
    if (!img_is_resizable($asset) || $w > (asset_dims($asset)[0] ?? 0)) {
        return null;
    }
    if ($existing = img_variant_file($asset, $w)) {
        return IMG_UPLOADS_URL . '/_v/' . basename($existing);
    }

    $src = img_uploads_dir() . '/' . ltrim($asset['path'], '/');
    $info = @getimagesize($src);
    if (!$info || !function_exists('imagecreatetruecolor')) {
        return null;
    }
    [$w0, $h0, $type] = $info;

    $im = match ($type) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($src),
        IMAGETYPE_PNG => @imagecreatefrompng($src),
        IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($src) : false,
        default => false,
    };
    if (!$im) {
        return null;
    }
    if ($type === IMAGETYPE_JPEG && ($orientation = img_orientation($asset)) > 1) {
        $im = img_apply_orientation($im, $orientation);
        [$w0, $h0] = [imagesx($im), imagesy($im)];
    }

    $hasAlpha = $type !== IMAGETYPE_JPEG;
    $ext = function_exists('imagewebp') ? 'webp' : ($hasAlpha ? 'png' : 'jpg');

    $dir = img_uploads_dir() . '/_v';
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        imagedestroy($im);
        return null;
    }
    $final = $dir . '/' . img_variant_basename($asset, $w) . '.' . $ext;

    $lock = @fopen($final . '.lock', 'c');
    if ($lock) {
        flock($lock, LOCK_EX);
    }

    $tmp = null;
    try {
        if (!is_file($final)) {
            $h = max(1, (int) round($h0 * $w / $w0));
            $dst = imagecreatetruecolor($w, $h);
            if ($hasAlpha) {
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
                imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
            }
            imagecopyresampled($dst, $im, 0, 0, 0, 0, $w, $h, $w0, $h0);

            $tmp = $final . '.' . getmypid() . '.tmp';
            $ok = match ($ext) {
                'webp' => imagewebp($dst, $tmp, IMG_WEBP_QUALITY),
                'png' => imagepng($dst, $tmp, 7),
                default => imagejpeg($dst, $tmp, IMG_JPEG_QUALITY),
            };
            imagedestroy($dst);
            if (!$ok || !@rename($tmp, $final)) {
                @unlink($tmp);
                $tmp = null;
                return null;
            }
            $tmp = null;
        }
    } finally {
        imagedestroy($im);
        if ($tmp && is_file($tmp)) {
            @unlink($tmp);
        }
        if ($lock) {
            flock($lock, LOCK_UN);
            fclose($lock);
            @unlink($final . '.lock');
        }
    }

    return IMG_UPLOADS_URL . '/_v/' . basename($final);
}

/**
 * Downscale and compress a master image file in-place if its longest side exceeds $maxDim.
 * Handles EXIF orientation (rotates upright for JPEG) and preserves alpha transparency (PNG/WebP).
 *
 * @param string $filePath Full filesystem path to the master image
 * @param int $maxDim Maximum width or height allowed (default 1920)
 * @return array|null Returns array of ['width' => int, 'height' => int, 'size' => int, 'hash' => string, 'scaled' => bool] or null on failure.
 */
function img_optimize_master_file(string $filePath, int $maxDim = IMG_MAX_DIMENSION): ?array {
    if (!is_file($filePath) || !is_readable($filePath)) {
        return null;
    }

    $info = @getimagesize($filePath);
    if (!$info || !function_exists('imagecreatetruecolor')) {
        return null;
    }

    [$w0, $h0, $type] = $info;
    if ($w0 <= 0 || $h0 <= 0) {
        return null;
    }

    if (!in_array($type, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
        return null;
    }

    // Read EXIF orientation for JPEGs
    $orientation = 1;
    if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $exif = @exif_read_data($filePath);
        $orientation = (int) ($exif['Orientation'] ?? 1);
        if ($orientation < 1 || $orientation > 8) {
            $orientation = 1;
        }
    }

    $displayWidth = $orientation >= 5 ? $h0 : $w0;
    $displayHeight = $orientation >= 5 ? $w0 : $h0;
    $maxDimension = max($displayWidth, $displayHeight);

    $needsResize = ($maxDimension > $maxDim);
    $needsRotation = ($orientation > 1);

    if (!$needsResize && !$needsRotation) {
        return [
            'width' => $w0,
            'height' => $h0,
            'size' => (int) filesize($filePath),
            'hash' => (string) hash_file('md5', $filePath),
            'scaled' => false,
        ];
    }

    $scale = $needsResize ? ($maxDim / $maxDimension) : 1.0;
    $targetW = max(1, (int) round($displayWidth * $scale));
    $targetH = max(1, (int) round($displayHeight * $scale));

    $im = match ($type) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($filePath),
        IMAGETYPE_PNG => @imagecreatefrompng($filePath),
        IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($filePath) : false,
        default => false,
    };

    if (!$im) {
        return null;
    }

    if ($needsRotation) {
        $im = img_apply_orientation($im, $orientation);
        $w0 = imagesx($im);
        $h0 = imagesy($im);
    }

    $hasAlpha = $type !== IMAGETYPE_JPEG;
    $dst = imagecreatetruecolor($targetW, $targetH);
    if (!$dst) {
        imagedestroy($im);
        return null;
    }

    if ($hasAlpha) {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
    }

    imagecopyresampled($dst, $im, 0, 0, 0, 0, $targetW, $targetH, $w0, $h0);
    imagedestroy($im);

    $tmp = $filePath . '.' . getmypid() . '.' . uniqid() . '.tmp';
    $ok = match ($type) {
        IMAGETYPE_JPEG => imagejpeg($dst, $tmp, IMG_JPEG_QUALITY),
        IMAGETYPE_PNG => imagepng($dst, $tmp, 8),
        IMAGETYPE_WEBP => imagewebp($dst, $tmp, IMG_WEBP_QUALITY),
        default => false,
    };
    imagedestroy($dst);

    if (!$ok || !@rename($tmp, $filePath)) {
        if (is_file($tmp)) {
            @unlink($tmp);
        }
        return null;
    }

    clearstatcache(true, $filePath);

    return [
        'width' => $targetW,
        'height' => $targetH,
        'size' => (int) filesize($filePath),
        'hash' => (string) hash_file('md5', $filePath),
        'scaled' => true,
    ];
}

/**
 * Ensures an asset's master file does not exceed IMG_MAX_DIMENSION.
 * If resized, updates the asset record in Cockpit's dataStorage ('assets')
 * and recursively updates references in collections (projects, categories, singletons).
 */
function img_ensure_master_optimized(array $asset, $app = null): array {
    if (empty($asset['path']) || empty($asset['_id']) || !img_is_resizable($asset)) {
        return $asset;
    }

    $app = $app ?? cockpit_app();
    $filePath = img_uploads_dir() . '/' . ltrim($asset['path'], '/');

    $res = img_optimize_master_file($filePath, IMG_MAX_DIMENSION);
    if (!$res || empty($res['scaled'])) {
        return $asset;
    }

    $asset['width'] = $res['width'];
    $asset['height'] = $res['height'];
    $asset['size'] = $res['size'];
    $asset['_hash'] = $res['hash'];
    $asset['_modified'] = time();

    if ($app) {
        $app->dataStorage->save('assets', $asset);
        img_sync_asset_references_in_content($app, $asset);
    }

    return $asset;
}

/**
 * Recursively syncs an asset's updated width, height, size, and hash across all collection documents.
 */
function img_sync_asset_references_in_content($app, array $asset): void {
    $assetId = $asset['_id'] ?? '';
    if (!$assetId || !$app) return;

    $updateAssetRef = function (&$data) use (&$updateAssetRef, $asset, $assetId, &$changed) {
        if (!is_array($data)) return;
        if (isset($data['_id']) && $data['_id'] === $assetId && isset($data['path'])) {
            $data['width'] = $asset['width'];
            $data['height'] = $asset['height'];
            $data['size'] = $asset['size'];
            $data['_hash'] = $asset['_hash'];
            $changed = true;
            return;
        }
        foreach ($data as &$val) {
            $updateAssetRef($val);
        }
    };

    // 1. Projects collection
    foreach ($app->dataStorage->find('content/collections/projects')->toArray() as $doc) {
        $changed = false;
        $updateAssetRef($doc);
        if ($changed) {
            $app->dataStorage->save('content/collections/projects', $doc);
        }
    }

    // 2. Categories collection
    foreach ($app->dataStorage->find('content/collections/categories')->toArray() as $doc) {
        $changed = false;
        $updateAssetRef($doc);
        if ($changed) {
            $app->dataStorage->save('content/collections/categories', $doc);
        }
    }

    // 3. Singletons
    foreach ($app->dataStorage->find('content/singletons')->toArray() as $doc) {
        $changed = false;
        $updateAssetRef($doc);
        if ($changed) {
            $app->dataStorage->save('content/singletons', $doc);
        }
    }
}
