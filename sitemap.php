<?php
/**
 * Dynamic XML Sitemap Generator
 */

require_once __DIR__ . '/includes/helpers.php';

header('Content-Type: application/xml; charset=UTF-8');

$app = Cockpit::instance();
$content = $app->module('content');

$allCategories = $content->items('categories', [
    'filter' => ['_state' => 1],
    'fields' => ['slug' => 1, '_modified' => 1],
]);
$allProjects = $content->items('projects', [
    'filter' => ['_state' => 1],
    'fields' => ['slug' => 1, 'categories' => 1, '_modified' => 1],
]);

$lastmod = fn(int $ts) => date('Y-m-d', $ts ?: time());

// Latest change per category: its own edit or any of its published projects
$categoryModified = [];
foreach ($allCategories as $cat) {
    $categoryModified[$cat['_id']] = (int)($cat['_modified'] ?? 0);
}
$latestProject = 0;
foreach ($allProjects as $proj) {
    $modified = (int)($proj['_modified'] ?? 0);
    $latestProject = max($latestProject, $modified);
    $cats = $proj['categories'] ?? [];
    if (!is_array($cats) || isset($cats['_id'])) $cats = [$cats];
    foreach ($cats as $cat) {
        $catId = is_array($cat) ? ($cat['_id'] ?? '') : $cat;
        if (isset($categoryModified[$catId])) {
            $categoryModified[$catId] = max($categoryModified[$catId], $modified);
        }
    }
}

// Homepage changes when the home tiles, settings or any project change
$homeModified = max(
    $latestProject,
    (int)(getHomePage()['_modified'] ?? 0),
    (int)(getSiteSettings()['_modified'] ?? 0)
);

echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">

<?php
// Homepage for each locale
foreach (LOCALES as $loc) {
    echo '<url><loc>' . e(SITE_URL . localePath($loc)) . '</loc><lastmod>' . $lastmod($homeModified) . '</lastmod></url>' . "\n";
}

// Categories
foreach ($allCategories as $cat) {
    $slug = $cat['slug'] ?? '';
    if (!$slug) continue;
    $modified = $lastmod($categoryModified[$cat['_id']] ?? 0);
    foreach (LOCALES as $loc) {
        echo '<url><loc>' . e(SITE_URL . categoryPath($loc, $slug)) . '</loc><lastmod>' . $modified . '</lastmod></url>' . "\n";
    }
}

// Published projects
foreach ($allProjects as $proj) {
    $slug = $proj['slug'] ?? '';
    if (!$slug) continue;
    $modified = $lastmod((int)($proj['_modified'] ?? 0));
    foreach (LOCALES as $loc) {
        echo '<url><loc>' . e(SITE_URL . projectPath($loc, $slug)) . '</loc><lastmod>' . $modified . '</lastmod></url>' . "\n";
    }
}
?>

</urlset>
