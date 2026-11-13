<?php
/**
 * Cockpit CMS Custom Configuration
 *
 * No secrets in this file: they come from vorud/.env (see .env.example).
 */

// Automatically ensure all required Cockpit storage directories exist
foreach (['cache', 'data', 'tmp', 'uploads', 'content', 'logs'] as $dir) {
    $path = __DIR__ . '/../storage/' . $dir;
    if (!is_dir($path)) {
        @mkdir($path, 0755, true);
    }
}

// Cockpit (when debug is off, i.e. in production) lists the module and addon
// folders once in storage/cache/modules.cache.php and never rescans them.
// Deploys don't ship that cache and extracting a zip doesn't delete it, so a
// newly added addon (e.g. ModelSync) would never load on the server. Drop the
// cache when it no longer matches the folders on disk; Cockpit rebuilds it
// later in this same request.
(function () {
    $cacheFile = __DIR__ . '/../storage/cache/modules.cache.php';
    if (!is_file($cacheFile)) return;

    $cached = (string) @file_get_contents($cacheFile);
    $stale = false;

    // Every module/addon folder on disk must be listed...
    foreach (['modules', 'addons'] as $folder) {
        foreach (glob(__DIR__ . "/../{$folder}/*", GLOB_ONLYDIR) ?: [] as $dir) {
            if (!str_contains($cached, "'" . realpath($dir) . "'")) {
                $stale = true;
                break 2;
            }
        }
    }

    // ...and every listed folder must still exist
    if (!$stale && preg_match_all("~=>\\s*'([^']+)',~", $cached, $m)) {
        foreach ($m[1] as $path) {
            if (!is_dir($path)) {
                $stale = true;
                break;
            }
        }
    }

    if ($stale) {
        @unlink($cacheFile);
        if (function_exists('opcache_invalidate')) @opcache_invalidate($cacheFile, true);
    }
})();

// Secret used to sign Cockpit JWTs (2FA, password reset, CSRF tokens).
// Prefer COCKPIT_SEC_KEY from vorud/.env; otherwise generate a random key once
// and keep it in storage/data (never served over HTTP), so the public default
// key from Cockpit's source is never used.
$secKey = (string) env('COCKPIT_SEC_KEY', '');
if (strlen($secKey) < 32) {
    $secKeyFile = __DIR__ . '/../storage/data/.sec-key';
    $secKey = is_file($secKeyFile) ? trim((string) file_get_contents($secKeyFile)) : '';
    if (strlen($secKey) < 32) {
        $secKey = bin2hex(random_bytes(32));
        @file_put_contents($secKeyFile, $secKey, LOCK_EX);
        @chmod($secKeyFile, 0600);
    }
}

return [
    'app.name' => 'Amordad Architecture Studio',

    'sec-key' => $secKey,

    // Admin session cookie is never sent on cross-site requests (CSRF defence)
    'session.cookie' => [
        'samesite' => 'Strict',
    ],

    // Only plain image/document uploads (no SVG/HTML that could carry scripts)
    'assets' => [
        'allowed_uploads' => 'jpg,jpeg,png,webp,gif,pdf',
    ],
];
