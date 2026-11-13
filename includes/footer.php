<?php
/**
 * Shared Footer Template
 *
 * Expected variables:
 *   $locale, $settings, $dir
 */

$footerLogo = $settings['footerLogo'] ?? $settings['headerLogo'] ?? null;
$footerLogoSrc = mediaUrl($footerLogo, 'logo');
$footerLogoLabel = localized($settings, 'footerLogoLabel', $locale);
$copyright = localized($settings, 'copyright', $locale);
$currentPath = currentUrl();
?>

</main>

<footer class="footer">
    <div class="site-container">
        <div class="footer__inner">
            <!-- Copyright -->
            <p class="footer__copyright"><?= e(formatCopyright($copyright, $locale)) ?></p>

            <!-- Locale switcher (flags + text buttons) -->
            <nav aria-label="<?= e(ui('languageNav', $locale)) ?>" class="footer__locale-nav">
                <?php foreach (LOCALES as $loc): ?>
                <a href="<?= e(switchLocalePath($locale, $loc, $currentPath)) ?>"
                   lang="<?= e($loc) ?>"
                   class="footer__locale-btn <?= $loc === $locale ? 'footer__locale-btn--active' : '' ?>"
                   <?= $loc === $locale ? 'aria-current="true"' : '' ?>>
                    <img src="/assets/images/flags/<?= e(LOCALE_FLAG_ICONS[$loc]) ?>.svg"
                         alt=""
                         class="footer__locale-flag"
                         width="18"
                         height="14"
                         loading="lazy">
                    <span><?= e(LOCALE_LABELS[$loc]) ?></span>
                </a>
                <?php endforeach; ?>
            </nav>

            <!-- Logo + Label -->
            <div class="footer__brand">
                <a href="<?= e(localePath($locale)) ?>" style="display:flex;flex-direction:column;align-items:inherit">
                    <?php if ($footerLogoSrc): ?>
                    <img src="<?= e($footerLogoSrc) ?>" alt="<?= e($footerLogoLabel ?: ($siteName ?? 'Amordad')) ?>"
                         class="footer__logo-img" width="64" height="64" loading="lazy">
                    <?php else: ?>
                    <span class="footer__logo-placeholder">AD</span>
                    <?php endif; ?>
                    <?php if ($footerLogoLabel): ?>
                    <span class="footer__logo-label"><?= e($footerLogoLabel) ?></span>
                    <?php endif; ?>
                </a>
            </div>
        </div>
    </div>
</footer>

<!-- Lightbox overlay (used by project gallery) -->
<div id="lightbox" class="lightbox-overlay" role="dialog" aria-modal="true" aria-label="<?= e(ui('imageViewer', $locale)) ?>" dir="ltr" hidden>
    <button type="button" class="lightbox-close" aria-label="<?= e(ui('close', $locale)) ?>">&times;</button>
    <button type="button" class="lightbox-prev" aria-label="<?= e(ui('previous', $locale)) ?>">
        <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <polyline points="15 18 9 12 15 6"></polyline>
        </svg>
    </button>
    <img alt="">
    <button type="button" class="lightbox-next" aria-label="<?= e(ui('next', $locale)) ?>">
        <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <polyline points="9 18 15 12 9 6"></polyline>
        </svg>
    </button>
</div>

<script src="<?= e(assetUrl('/assets/js/app.js')) ?>"></script>
</body>
</html>
