<?php
/**
 * Cockpit CMS Addon: ModelSync
 *
 * Cockpit (when debug is off, i.e. in production) caches the content model
 * definitions (storage/content/*.model.php) in app.memory and never notices
 * when those files change on disk. Code-only deploys replace the model files
 * but not the memory database, so production would keep serving the old
 * models. This addon compares a signature of the model files (name + mtime +
 * size) with the one stored at the last refresh and rebuilds the cache when
 * they differ.
 *
 * Cockpit builds the cache with `include`, so with OPcache enabled it could
 * read a stale compiled copy of a model file that just changed. The files are
 * invalidated first (as Cockpit itself does after saving a model). If OPcache
 * refuses (e.g. opcache.restrict_api), the files are read directly instead of
 * through `include`, so a stale compiled copy is never used.
 *
 * Note: vorud/config/config.php makes sure this addon is actually loaded after
 * a deploy (Cockpit's module list cache would otherwise not include it).
 */

if ($this->retrieve('content/models/storage') === 'database') {
    return; // models live in the database, not in files
}

$contentDir = realpath(__DIR__ . '/../../storage/content');
$modelFiles = $contentDir ? (glob($contentDir . '/*.model.php') ?: []) : [];

$signature = md5(implode('|', array_map(
    fn($file) => basename($file) . ':' . filemtime($file) . ':' . filesize($file),
    $modelFiles
)));

if ($this->memory->get('modelsync.signature') !== $signature) {

    $opcacheOn = function_exists('opcache_invalidate')
        && (bool) ini_get('opcache.enable')
        && (PHP_SAPI !== 'cli' || (bool) ini_get('opcache.enable_cli'));

    // opcache_invalidate() returns true when a file was invalidated or was not
    // cached at all, and false only when OPcache refuses (e.g. restrict_api).
    $refused = false;
    if ($opcacheOn) {
        foreach ($modelFiles as $file) {
            if (!@opcache_invalidate($file, true)) {
                $refused = true;
            }
        }
    }

    if ($refused) {
        // Same result as Cockpit's cache(), but without `include`
        $models = [];
        foreach ($modelFiles as $file) {
            $store = eval('?>' . file_get_contents($file));
            if (is_array($store) && isset($store['name'])) {
                $models[$store['name']] = $store;
            }
        }
        ksort($models);
        $this->memory->set('content.models', $models);
    } else {
        $this->helper('content.model')->cache();
    }

    $this->memory->set('modelsync.signature', $signature);
}
