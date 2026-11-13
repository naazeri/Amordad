<?php
/**
 * Fast GD-based Image Thumbnail Generator & Cacher
 *
 * Usage: /thumb.php?src=/2026/09/19/image.jpg&w=600&h=0
 *
 * Only (w, h) pairs defined in IMAGE_SIZES are accepted, and only image files
 * inside vorud/storage/uploads can be read.
 */

require_once __DIR__ . '/includes/config.php';

const THUMB_QUALITY = 82;
const THUMB_CACHE_VERSION = 'v2';

function thumbFail(int $code, string $message): void {
    http_response_code($code);
    header('Content-Type: text/plain; charset=UTF-8');
    exit($message);
}

$src = $_GET['src'] ?? '';
$w   = $_GET['w'] ?? 0;
$h   = $_GET['h'] ?? 0;

if (!is_string($src) || $src === '' || !is_scalar($w) || !is_scalar($h)) {
    thumbFail(400, 'Invalid request');
}

$w = (int)$w;
$h = (int)$h;

// Only allow the preset sizes the site actually uses (or the original, 0x0)
$allowedSizes = [[0, 0]];
foreach (IMAGE_SIZES as $size) {
    $allowedSizes[] = [(int)$size['w'], (int)($size['h'] ?: 0)];
}
if (!in_array([$w, $h], $allowedSizes, true)) {
    thumbFail(400, 'Unsupported size');
}

// Security: resolve the real path and make sure it stays inside the uploads folder
$uploadsDir = realpath(__DIR__ . '/vorud/storage/uploads');
$src = preg_replace('#^/(?:cockpit|vorud)/storage/uploads/#', '/', $src);
$fullSrcPath = $uploadsDir ? realpath($uploadsDir . '/' . ltrim($src, '/')) : false;

if ($fullSrcPath === false
    || !str_starts_with($fullSrcPath, $uploadsDir . DIRECTORY_SEPARATOR)
    || !is_file($fullSrcPath)) {
    thumbFail(404, 'Image not found');
}

$ext = strtolower(pathinfo($fullSrcPath, PATHINFO_EXTENSION));
$mimes = [
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'webp' => 'image/webp',
    'gif'  => 'image/gif',
];

if (!isset($mimes[$ext])) {
    thumbFail(404, 'Image not found');
}
$mime = $mimes[$ext];

/**
 * Send a file with long-lived caching and ETag support.
 */
function thumbSendFile(string $path, string $mime): void {
    $etag = '"' . md5(filemtime($path) . '-' . filesize($path) . '-' . $path) . '"';
    if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
        http_response_code(304);
        exit;
    }
    header("Content-Type: {$mime}");
    header("ETag: {$etag}");
    header('Cache-Control: public, max-age=31536000, immutable');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}

// No resize requested: serve the original image
if ($w <= 0 && $h <= 0) {
    thumbSendFile($fullSrcPath, $mime);
}

// Cache lookup
$cacheDir = __DIR__ . '/vorud/storage/tmp/thumbs';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0775, true);
}

$relPath = substr($fullSrcPath, strlen($uploadsDir));
$cacheFile = $cacheDir . '/' . md5($relPath . "_{$w}x{$h}_" . THUMB_CACHE_VERSION) . ".{$ext}";

if (is_file($cacheFile) && filemtime($cacheFile) >= filemtime($fullSrcPath)) {
    thumbSendFile($cacheFile, $mime);
}

// Load original image
switch ($ext) {
    case 'jpg':
    case 'jpeg':
        $img = @imagecreatefromjpeg($fullSrcPath);
        break;
    case 'png':
        $img = @imagecreatefrompng($fullSrcPath);
        break;
    case 'webp':
        $img = @imagecreatefromwebp($fullSrcPath);
        break;
    case 'gif':
        $img = @imagecreatefromgif($fullSrcPath);
        break;
    default:
        $img = false;
}

if (!$img) {
    // Fallback: serve original
    thumbSendFile($fullSrcPath, $mime);
}

// Respect EXIF orientation (phone/camera photos)
if (($ext === 'jpg' || $ext === 'jpeg') && function_exists('exif_read_data')) {
    $exif = @exif_read_data($fullSrcPath);
    $orientation = (int)($exif['Orientation'] ?? 1);
    $rotated = match ($orientation) {
        3 => imagerotate($img, 180, 0),
        6 => imagerotate($img, -90, 0),
        8 => imagerotate($img, 90, 0),
        default => null,
    };
    if ($rotated) {
        imagedestroy($img);
        $img = $rotated;
    }
}

$origW = imagesx($img);
$origH = imagesy($img);

// Calculate target dimensions (preserving aspect ratio, never upscaling)
if ($w > 0 && $h <= 0) {
    $ratio = min(1, $w / $origW);
} elseif ($h > 0 && $w <= 0) {
    $ratio = min(1, $h / $origH);
} else {
    $ratio = min(1, $w / $origW, $h / $origH);
}
$targetW = max(1, (int)round($origW * $ratio));
$targetH = max(1, (int)round($origH * $ratio));

// Create new canvas
$thumb = imagecreatetruecolor($targetW, $targetH);

// Handle transparency for PNG and WebP
if ($ext === 'png' || $ext === 'webp') {
    imagealphablending($thumb, false);
    imagesavealpha($thumb, true);
    $transparent = imagecolorallocatealpha($thumb, 255, 255, 255, 127);
    imagefilledrectangle($thumb, 0, 0, $targetW, $targetH, $transparent);
}

imagecopyresampled($thumb, $img, 0, 0, 0, 0, $targetW, $targetH, $origW, $origH);
imagedestroy($img);

/**
 * Encode the thumbnail to a file path, or to the output when $path is null.
 */
function thumbEncode($thumb, string $ext, ?string $path): bool {
    return match ($ext) {
        'jpg', 'jpeg' => imagejpeg($thumb, $path, THUMB_QUALITY),
        'png'         => imagepng($thumb, $path, 8),
        'webp'        => imagewebp($thumb, $path, THUMB_QUALITY),
        'gif'         => imagegif($thumb, $path),
        default       => false,
    };
}

// Save to cache atomically: write a temp file, then rename it into place
$tmpFile = $cacheFile . '.' . getmypid() . '.tmp';
if (is_writable($cacheDir) && @thumbEncode($thumb, $ext, $tmpFile) && @rename($tmpFile, $cacheFile)) {
    imagedestroy($thumb);
    thumbSendFile($cacheFile, $mime);
}
@unlink($tmpFile);

// Cache not writable: stream the generated image directly
header("Content-Type: {$mime}");
header('Cache-Control: public, max-age=86400');
thumbEncode($thumb, $ext, null);
imagedestroy($thumb);
exit;
