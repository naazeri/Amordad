<?php
/**
 * PHP Built-in Webserver Router (Local Development)
 *
 * Emulates Apache .htaccess rewrite rules when running:
 *   php -S localhost:8000 router.php
 */

$rawUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = urldecode($rawUri);

// Mirror the .htaccess blocks for files that must never be served directly
if (preg_match('#^/(includes/|tools/|docs/|cockpit(/|$))|\.(zip|tar|gz|sql|sqlite[a-z-]*|bak|sh|md|log)$|(^|/)error_log$#i', $uri)) {
    http_response_code(404);
    require __DIR__ . '/page-404.php';
    exit;
}

// 1. Existing static files/directories
if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    return false; // Serve file directly
}

// 2. Cockpit admin
if (str_starts_with($uri, '/vorud')) {
    // Cockpit's cli-server support derives its index.php from SCRIPT_FILENAME;
    // for "dot" routes (e.g. /vorud/app.i18n.data.js) that would be this router
    // in the project root, which would run the site's index.php instead.
    $_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/vorud/index.php';
    require __DIR__ . '/vorud/index.php';
    exit;
}

// 2b. Image variant endpoint: /img/{id}/{width}
if (preg_match('#^/img/([a-z0-9]+)/([0-9]+)/?$#i', $uri, $m)) {
    $_GET['id'] = $m[1];
    $_GET['w']  = (int) $m[2];
    require __DIR__ . '/img.php';
    exit;
}

// 3. Sitemap
if ($uri === '/sitemap.xml') {
    require __DIR__ . '/sitemap.php';
    exit;
}

// 4. Root redirect (locale negotiation lives in index.php)
if ($uri === '/' || $uri === '') {
    require __DIR__ . '/index.php';
    exit;
}


// 5. Category page: /{locale}/category/{slug}
if (preg_match('#^/(fa|tr|en)/category/([a-z0-9-]+)/?$#', $uri, $m)) {
    $_GET['locale'] = $m[1];
    $_GET['slug']   = $m[2];
    require __DIR__ . '/page-category.php';
    exit;
}

// 6. Project detail page: /{locale}/project/{slug}
if (preg_match('#^/(fa|tr|en)/project/([a-z0-9-]+)/?$#', $uri, $m)) {
    $_GET['locale'] = $m[1];
    $_GET['slug']   = $m[2];
    require __DIR__ . '/page-project.php';
    exit;
}

// 7. Homepage: /{locale}/
if (preg_match('#^/(fa|tr|en)/?$#', $uri, $m)) {
    $_GET['locale'] = $m[1];
    require __DIR__ . '/page-home.php';
    exit;
}

// 8. Fallback 404
http_response_code(404);
require __DIR__ . '/page-404.php';
exit;
