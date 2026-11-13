<?php
/**
 * Shared page bootstrap — included at the top of every page template.
 *
 * Sets up: $locale, $dir, $settings, $categories, $fontStack.
 */

require_once __DIR__ . '/helpers.php';

$locale = getCurrentLocale();
$dir = getDirection($locale);
$settings = getSiteSettings();
$categories = getNavigationCategories();

// Font setup (Vazirmatn is loaded locally for all locales)
$fontStack = getFontStack();
