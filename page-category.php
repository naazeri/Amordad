<?php
/**
 * Category Listing Page — 3-column project grid
 */

require_once __DIR__ . '/includes/bootstrap.php';

$slug = $_GET['slug'] ?? '';
if (!is_string($slug) || !preg_match('/^[a-z0-9-]{1,64}$/', $slug)) {
    http_response_code(404);
    require __DIR__ . '/page-404.php';
    exit;
}

$category = getCategoryBySlug($slug);
if (!$category) {
    http_response_code(404);
    require __DIR__ . '/page-404.php';
    exit;
}

$categoryName = localized($category, 'name', $locale);
$categoryId = $category['_id'] ?? '';
$siteName = localized($settings, 'siteName', $locale) ?: 'Amordad';

$projects = getProjectsByCategory($categoryId);

// Meta tags
$metaTags = [
    'title'       => $categoryName . ' — ' . $siteName,
    'description' => localizedStrict($category, 'description', $locale)
                     ?: localizedStrict($settings, 'defaultMetaDescription', $locale),
    'ogImage'     => !empty($settings['defaultOgImage']) ? SITE_URL . mediaUrl($settings['defaultOgImage'], 'og') : '',
    'url'         => SITE_URL . categoryPath($locale, $slug),
    'locale'      => $locale,
    'path'        => categoryPath($locale, $slug),
];
$pageTitle = $metaTags['title'];

require_once __DIR__ . '/includes/header.php';
?>

<div class="site-container" style="padding-top:4rem;padding-bottom:6rem">
    <h1 class="sr-only"><?= e($categoryName) ?></h1>
    <div class="project-grid" dir="<?= e($dir) ?>">
        <?php $renderedCards = 0; ?>
        <?php foreach ($projects as $project): ?>
        <?php
            $title = localized($project, 'title', $locale);
            $cardImage = projectCardImage($project);
            $imgSrc = mediaUrl($cardImage, 'card');
            $projectSlug = $project['slug'] ?? '';
            if (!$projectSlug) continue;
            $renderedCards++;
        ?>
        <a href="<?= e(projectPath($locale, $projectSlug)) ?>" class="project-card">
            <?php if ($imgSrc): ?>
            <img src="<?= e($imgSrc) ?>"
                 alt="<?= mediaAlt($cardImage, $title, $locale) ?>"
                 class="project-card__image"
                 loading="<?= $renderedCards <= 3 ? 'eager' : 'lazy' ?>"
                 width="280" height="280">
            <?php else: ?>
            <span class="project-card__image project-card__image--empty" aria-hidden="true"></span>
            <?php endif; ?>
            <span class="project-card__title"><?= e($title) ?></span>
        </a>
        <?php endforeach; ?>

        <?php if ($renderedCards === 0): ?>
        <p class="project-grid__empty">
            <?= e(ui('noProjects', $locale)) ?>
        </p>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
