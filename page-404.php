<?php
/**
 * 404 — Not Found page
 *
 * Reached either directly through the .htaccess fallback, or required by a page
 * template (after helpers.php is loaded) when an item does not exist.
 */
require_once __DIR__ . '/includes/config.php';

if (!function_exists('getCurrentLocaleFromPath')) {
    function getCurrentLocaleFromPath(): string {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        if (is_string($path) && preg_match('#^/(fa|tr|en)(/|$)#', $path, $m)) {
            return $m[1];
        }
        return DEFAULT_LOCALE;
    }
}

if (!function_exists('getDirection')) {
    function getDirection(string $locale): string {
        return in_array($locale, RTL_LOCALES, true) ? 'rtl' : 'ltr';
    }
}

$locale = isset($locale) && in_array($locale, LOCALES, true) ? $locale : getCurrentLocaleFromPath();
$dir = getDirection($locale);
$t = fn(string $key) => htmlspecialchars(UI_STRINGS[$key][$locale] ?? UI_STRINGS[$key]['en'], ENT_QUOTES, 'UTF-8');

http_response_code(404);
?>
<!DOCTYPE html>
<html lang="<?= $locale ?>" dir="<?= $dir ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>404 — <?= $t('notFoundTitle') ?></title>
    <!-- Preload local Vazirmatn variable font -->
    <link rel="preload" href="/assets/fonts/Vazirmatn-Variable.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/assets/css/styles.css?v=<?= (int)@filemtime(__DIR__ . '/assets/css/styles.css') ?>">
</head>
<body>
    <main class="page-404">
        <h1>404</h1>
        <p><?= $t('notFoundTitle') ?></p>
        <p style="margin-top:2rem">
            <a href="/<?= $locale ?>/" style="text-decoration:underline"><?= $t('backHome') ?></a>
        </p>
    </main>
</body>
</html>
