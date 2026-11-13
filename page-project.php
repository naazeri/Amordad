<?php
/**
 * Project Detail Page — hero image, tabs, content, gallery with lightbox
 */

require_once __DIR__ . '/includes/bootstrap.php';

$slug = $_GET['slug'] ?? '';
if (!is_string($slug) || !preg_match('/^[a-z0-9-]{1,64}$/', $slug)) {
    http_response_code(404);
    require __DIR__ . '/page-404.php';
    exit;
}

$project = getProjectBySlug($slug);
if (!$project) {
    http_response_code(404);
    require __DIR__ . '/page-404.php';
    exit;
}

$title = localized($project, 'title', $locale);
$featured = $project['featuredImage'] ?? null;
$heroSrc = mediaUrl($featured, 'hero');

// Rich text content (stored as HTML from WYSIWYG), sanitized on render
$projectInfo = sanitizeRichText(localized($project, 'projectInformation', $locale));
$description = sanitizeRichText(localized($project, 'description', $locale));
$content = sanitizeRichText(localized($project, 'content', $locale));

// Gallery images
$galleryItems = $project['projectGallery'] ?? [];
if (!is_array($galleryItems)) $galleryItems = [];

// Meta description in the page language only (never Persian on en/tr pages)
$metaDescription = textExcerpt(
    localizedStrict($project, 'description', $locale)
    ?: localizedStrict($project, 'projectInformation', $locale)
    ?: localizedStrict($project, 'content', $locale)
);

// Meta tags
$metaTags = [
    'title'       => $title . ' — ' . (localized($settings, 'siteName', $locale) ?: 'Amordad'),
    'description' => $metaDescription,
    'ogImage'     => $heroSrc ? SITE_URL . mediaUrl($featured, 'og') : '',
    'url'         => SITE_URL . projectPath($locale, $slug),
    'locale'      => $locale,
    'path'        => projectPath($locale, $slug),
    'type'        => 'article',
];
$pageTitle = $metaTags['title'];

require_once __DIR__ . '/includes/header.php';

$isRtl = isRtl($locale);
$textAlign = $isRtl ? 'text-align:right' : 'text-align:left';

// Only render tabs that have content; the first one is active
$tabs = [];
if ($projectInfo !== '' || !empty($galleryItems)) {
    $tabs[] = ['id' => 'info', 'label' => TAB_LABELS['projectInformation'][$locale] ?? 'Project Information', 'html' => $projectInfo];
}
if ($description !== '') {
    $tabs[] = ['id' => 'desc', 'label' => TAB_LABELS['description'][$locale] ?? 'Description', 'html' => $description];
}
?>

<div class="site-container site-container--narrow" style="padding-top:2.5rem;padding-bottom:3.5rem">
    <article>
        <!-- Title -->
        <h1 class="project-title" style="<?= $textAlign ?>"><?= e($title) ?></h1>

        <!-- Hero Image -->
        <?php if ($heroSrc): ?>
        <?php [$heroW, $heroH] = mediaDimensions($featured, 1200, 0.5625); ?>
        <div class="project-hero">
            <img src="<?= e($heroSrc) ?>"
                 srcset="<?= e(mediaSrcset($featured, ['card', 'hero-md', 'hero'])) ?>"
                 sizes="(min-width: 800px) 760px, 100vw"
                 alt="<?= mediaAlt($featured, $title, $locale) ?>"
                 width="<?= $heroW ?>" height="<?= $heroH ?>"
                 fetchpriority="high"
                 style="width:100%;height:auto">
        </div>
        <?php endif; ?>

        <!-- Tabs: Project Information & Description -->
        <?php if ($tabs): ?>
        <div class="project-tabs" dir="<?= e($dir) ?>" style="<?= $textAlign ?>">
            <div class="project-tabs__nav" role="tablist" aria-label="<?= e(ui('projectDetails', $locale)) ?>">
                <?php foreach ($tabs as $i => $tab): ?>
                <button type="button" role="tab"
                        class="project-tabs__btn<?= $i === 0 ? ' project-tabs__btn--active' : '' ?>"
                        id="tab-<?= $tab['id'] ?>" aria-selected="<?= $i === 0 ? 'true' : 'false' ?>"
                        aria-controls="panel-<?= $tab['id'] ?>"
                        tabindex="<?= $i === 0 ? '0' : '-1' ?>"
                        data-tab-btn="<?= $tab['id'] ?>">
                    <?= e($tab['label']) ?>
                </button>
                <?php endforeach; ?>
            </div>

            <?php foreach ($tabs as $i => $tab): ?>
            <div class="project-tabs__panel richtext" role="tabpanel"
                 id="panel-<?= $tab['id'] ?>" aria-labelledby="tab-<?= $tab['id'] ?>"
                 data-tab-panel="<?= $tab['id'] ?>" tabindex="0"
                 dir="<?= e($dir) ?>" style="<?= $textAlign ?>"
                 <?= $i === 0 ? '' : 'hidden' ?>>
                <?= $tab['html'] ?>

                <?php if ($tab['id'] === 'info' && !empty($galleryItems)): ?>
                <div class="project-info-gallery">
                    <div class="project-info-gallery__track" role="region" aria-label="<?= e(ui('openImage', $locale)) ?>">
                        <?php $thumbIndex = 0; foreach ($galleryItems as $gi): ?>
                        <?php
                            $gImg = (is_array($gi) && isset($gi['image'])) ? $gi['image'] : $gi;
                            $gSrc = mediaUrl($gImg, 'hero');
                            if (!$gSrc) continue;
                            $thumbIndex++;
                            $gAlt = mediaAlt($gImg, $title . ' — ' . $thumbIndex, $locale);
                            $thumbSrc = mediaUrl($gImg, 'thumbnail');
                        ?>
                        <a href="<?= e($gSrc) ?>" class="project-info-gallery__item"
                           data-lightbox-src="<?= e($gSrc) ?>"
                           data-lightbox-alt="<?= $gAlt ?>"
                           aria-label="<?= e(ui('openImage', $locale)) ?>: <?= $gAlt ?>">
                            <img src="<?= e($thumbSrc) ?>"
                                 alt="<?= $gAlt ?>"
                                 loading="lazy"
                                 decoding="async"
                                 width="300" height="300">
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Content body -->
        <?php if ($content): ?>
        <?php $initialHideContent = !empty($tabs) && ($tabs[0]['id'] ?? '') === 'desc'; ?>
        <div class="project-content richtext" id="project-content"<?= $initialHideContent ? ' hidden' : '' ?> dir="<?= e($dir) ?>" style="margin-top:2rem;<?= $textAlign ?>">
            <?= $content ?>
        </div>
        <?php endif; ?>

        <!-- Project Gallery -->
        <?php if (!empty($galleryItems)): ?>
        <div class="project-gallery">
            <?php $galleryIndex = 0; ?>
            <?php foreach ($galleryItems as $gi): ?>
            <?php
                $galleryImg = (is_array($gi) && isset($gi['image'])) ? $gi['image'] : $gi;
                $gallerySrc = mediaUrl($galleryImg, 'hero');
                if (!$gallerySrc) continue;
                $galleryIndex++;
                $galleryAlt = mediaAlt($galleryImg, $title . ' — ' . $galleryIndex, $locale);
                [$cardW, $cardH] = mediaDimensions($galleryImg, 600);
            ?>
            <a href="<?= e($gallerySrc) ?>" class="project-gallery__btn"
               data-lightbox-src="<?= e($gallerySrc) ?>"
               data-lightbox-alt="<?= $galleryAlt ?>"
               aria-label="<?= e(ui('openImage', $locale)) ?>: <?= $galleryAlt ?>">
                <img src="<?= e(mediaUrl($galleryImg, 'card')) ?>"
                     alt="<?= $galleryAlt ?>"
                     loading="lazy" width="<?= $cardW ?>" height="<?= $cardH ?>">
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    </article>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
