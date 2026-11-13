<?php
/**
 * Homepage — 2×2 tile grid
 */

require_once __DIR__ . '/includes/bootstrap.php';

$homePage = getHomePage();
$tiles = $homePage['tiles'] ?? [];

// Build meta tags
$metaTags = [
    'title'       => localized($settings, 'defaultMetaTitle', $locale) ?: localized($settings, 'siteName', $locale) ?: 'Amordad',
    'description' => localizedStrict($settings, 'defaultMetaDescription', $locale),
    'ogImage'     => !empty($settings['defaultOgImage']) ? SITE_URL . mediaUrl($settings['defaultOgImage'], 'og') : '',
    'url'         => SITE_URL . localePath($locale),
    'locale'      => $locale,
    'path'        => localePath($locale),
];
$pageTitle = $metaTags['title'];

require_once __DIR__ . '/includes/header.php';
?>

<div class="site-container" style="padding-top:2rem;padding-bottom:3rem">
    <h1 class="sr-only"><?= e(localized($settings, 'siteName', $locale) ?: 'Amordad') ?></h1>
    <div class="home-grid">
        <?php $renderedTiles = 0; ?>
        <?php foreach ($tiles as $tile): ?>
        <?php
            $label = localized($tile, 'label', $locale);
            // Get linked category slug
            $tileCategory = $tile['category'] ?? null;
            $catSlug = '';
            if (is_array($tileCategory)) {
                $catSlug = $tileCategory['slug'] ?? '';
            }
            // Skip tiles whose category is missing or not published (it would link to a 404)
            if (!$catSlug || (int)($tileCategory['_state'] ?? 0) !== 1) continue;

            // Get images
            $tileImages = $tile['images'] ?? [];
            if (!is_array($tileImages)) $tileImages = [$tileImages];
            $tileImages = array_filter($tileImages);
            if (empty($tileImages)) continue;

            // Base layout on tiles actually rendered, so a skipped tile keeps the pattern
            $index = $renderedTiles++;
            $labelOnLeft = ($index % 2 === 0);
        ?>
        <a href="<?= e(categoryPath($locale, $catSlug)) ?>" class="home-tile">
            <?php if ($labelOnLeft): ?>
            <div class="home-tile__label"><span><?= e($label) ?></span></div>
            <?php endif; ?>

            <div class="home-tile__image-wrap" <?= count($tileImages) > 1 ? 'data-tile-images' : '' ?>>
                <?php foreach (array_values($tileImages) as $imgIdx => $img): ?>
                <?php if ($imgIdx === 0): ?>
                <img src="<?= e(mediaUrl($img, 'tile')) ?>"
                     alt="<?= mediaAlt($img, $label, $locale) ?>"
                     loading="<?= $index < 2 ? 'eager' : 'lazy' ?>"
                     <?= $index === 0 ? 'fetchpriority="high"' : '' ?>>
                <?php else: ?>
                <?php /* Rotation images are loaded by app.js after page load */ ?>
                <img data-src="<?= e(mediaUrl($img, 'tile')) ?>"
                     alt="" aria-hidden="true"
                     class="hidden">
                <?php endif; ?>
                <?php endforeach; ?>
            </div>

            <?php if (!$labelOnLeft): ?>
            <div class="home-tile__label"><span><?= e($label) ?></span></div>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
