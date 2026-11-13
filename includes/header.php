<?php
/**
 * Shared Header Template
 *
 * Expected variables before including this file:
 *   $locale      - Current locale code ('fa', 'en', 'tr')
 *   $dir         - Text direction ('rtl' or 'ltr')
 *   $settings    - Site settings from Cockpit singleton
 *   $categories  - Navigation categories array
 *   $pageTitle   - Page title for <title> tag
 *   $metaTags    - Array of meta tag data (optional, for renderMetaTags())
 *   $fontStack   - Font stack CSS value
 */

$headerLogo = $settings['headerLogo'] ?? null;
$logoSrc = mediaUrl($headerLogo, 'logo');
$siteName = localized($settings, 'siteName', $locale) ?: 'Amordad';
$currentPath = currentUrl();

// Determine active category slug from URL
$activeCategorySlug = '';
if (preg_match('#/category/([a-z0-9-]+)#', $currentPath, $m)) {
    $activeCategorySlug = $m[1];
}
?>
<!DOCTYPE html>
<html lang="<?= e($locale) ?>" dir="<?= e($dir) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php if (!empty($metaTags)) renderMetaTags($metaTags + ['siteName' => $siteName]); ?>
    <?php renderOrganizationJsonLd($settings, $locale); ?>
    <?php if ($logoSrc): ?>
    <link rel="icon" href="<?= e($logoSrc) ?>">
    <link rel="apple-touch-icon" href="<?= e($logoSrc) ?>">
    <?php endif; ?>
    <!-- Preload local Vazirmatn variable font -->
    <link rel="preload" href="/assets/fonts/Vazirmatn-Variable.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= e(assetUrl('/assets/css/styles.css')) ?>">
    <?php if (!empty($fontStack)): ?>
    <style>:root { --font-body: <?= $fontStack ?>; } body { font-family: var(--font-body); }</style>
    <?php endif; ?>
    <noscript>
        <style>
            .project-tabs__panel[hidden] { display: block !important; margin-top: 1rem; border-top: 1px dashed #d4d4d4; }
        </style>
    </noscript>
</head>
<body>

<a class="skip-link" href="#main"><?= e(ui('skipToContent', $locale)) ?></a>

<header class="header">
    <div class="site-container">
        <div class="header__inner">
            <!-- Mobile menu button -->
            <button type="button" class="header__mobile-btn" id="mobile-menu-btn" aria-expanded="false" aria-controls="mobile-nav">
                <?= e(ui('menu', $locale)) ?>
            </button>

            <!-- Desktop navigation -->
            <nav aria-label="<?= e(ui('mainNav', $locale)) ?>" class="header__nav header__nav--desktop">
                <!-- Locale switcher -->
                <select class="locale-select" data-locale-switcher aria-label="<?= e(ui('language', $locale)) ?>">
                    <?php foreach (LOCALES as $loc): ?>
                    <option value="<?= e(switchLocalePath($locale, $loc, $currentPath)) ?>" <?= $loc === $locale ? 'selected' : '' ?>><?= e(LOCALE_LABELS[$loc]) ?></option>
                    <?php endforeach; ?>
                </select>

                <?php foreach ($categories as $category): ?>
                <?php
                    $catName = localized($category, 'name', $locale);
                    $catSlug = $category['slug'] ?? '';
                    $isActive = $activeCategorySlug === $catSlug;
                ?>
                <a href="<?= e(categoryPath($locale, $catSlug)) ?>"
                   class="header__nav-link <?= $isActive ? 'header__nav-link--active' : '' ?>"
                   <?= $isActive ? 'aria-current="page"' : '' ?>>
                    <?= e($catName) ?>
                </a>
                <?php endforeach; ?>
            </nav>

            <!-- Logo -->
            <a href="<?= e(localePath($locale)) ?>" style="flex-shrink:0">
                <?php if ($logoSrc): ?>
                <img src="<?= e($logoSrc) ?>" alt="<?= e($siteName) ?>"
                     class="header__logo-img" width="48" height="48">
                <?php else: ?>
                <span class="header__logo-placeholder" aria-label="<?= e($siteName) ?>">AD</span>
                <?php endif; ?>
            </a>
        </div>

        <!-- Mobile navigation -->
        <nav id="mobile-nav" class="header__mobile-nav" aria-label="<?= e(ui('mobileNav', $locale)) ?>">
            <select class="locale-select" data-locale-switcher aria-label="<?= e(ui('language', $locale)) ?>">
                <?php foreach (LOCALES as $loc): ?>
                <option value="<?= e(switchLocalePath($locale, $loc, $currentPath)) ?>" <?= $loc === $locale ? 'selected' : '' ?>>
                    <?= e(LOCALE_LABELS[$loc]) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <?php foreach ($categories as $category): ?>
            <a href="<?= e(categoryPath($locale, $category['slug'] ?? '')) ?>">
                <?= e(localized($category, 'name', $locale)) ?>
            </a>
            <?php endforeach; ?>
        </nav>
    </div>
</header>

<main id="main" tabindex="-1">
