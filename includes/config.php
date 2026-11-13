<?php
/**
 * Amordad — Site Configuration
 */

define('COCKPIT_DIR', __DIR__ . '/../vorud');

// Canonical origin for canonical/hreflang/og/sitemap URLs. The request Host is
// only trusted for local development, never in production.
define('PRODUCTION_URL', 'https://amordad.backends.ir');
$requestHost = $_SERVER['HTTP_HOST'] ?? '';
$isLocalDev = PHP_SAPI === 'cli-server'
    || preg_match('#^(localhost|127\.0\.0\.1|\[::1\])(:\d+)?$#', $requestHost);
if ($isLocalDev && $requestHost !== '') {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    define('SITE_URL', ($isHttps ? 'https://' : 'http://') . $requestHost);
} else {
    define('SITE_URL', PRODUCTION_URL);
}
define('LOCALES', ['fa', 'tr', 'en']);
define('DEFAULT_LOCALE', 'fa');
define('RTL_LOCALES', ['fa']);

define('LOCALE_LABELS', [
    'fa' => 'فارسی',
    'tr' => 'Türkçe',
    'en' => 'English',
]);

define('LOCALE_FLAG_ICONS', [
    'fa' => 'ir',
    'tr' => 'tr',
    'en' => 'us',
]);

define('OG_LOCALES', [
    'fa' => 'fa_IR',
    'tr' => 'tr_TR',
    'en' => 'en_US',
]);

define('TAB_LABELS', [
    'projectInformation' => [
        'fa' => 'اطلاعات پروژه',
        'en' => 'Project Information',
        'tr' => 'Proje Bilgileri',
    ],
    'description' => [
        'fa' => 'توضیحات',
        'en' => 'Description',
        'tr' => 'Açıklama',
    ],
]);

// Interface strings (visible text and ARIA labels)
define('UI_STRINGS', [
    'menu'          => ['fa' => 'منو', 'tr' => 'Menü', 'en' => 'Menu'],
    'mainNav'       => ['fa' => 'ناوبری اصلی', 'tr' => 'Ana menü', 'en' => 'Main navigation'],
    'mobileNav'     => ['fa' => 'ناوبری موبایل', 'tr' => 'Mobil menü', 'en' => 'Mobile navigation'],
    'language'      => ['fa' => 'زبان', 'tr' => 'Dil', 'en' => 'Language'],
    'languageNav'   => ['fa' => 'انتخاب زبان', 'tr' => 'Dil seçimi', 'en' => 'Language selection'],
    'skipToContent' => ['fa' => 'رفتن به محتوای اصلی', 'tr' => 'İçeriğe geç', 'en' => 'Skip to content'],
    'projectDetails'=> ['fa' => 'جزئیات پروژه', 'tr' => 'Proje detayları', 'en' => 'Project details'],
    'openImage'     => ['fa' => 'نمایش تصویر', 'tr' => 'Görseli aç', 'en' => 'Open image'],
    'imageViewer'   => ['fa' => 'نمایشگر تصویر', 'tr' => 'Görsel görüntüleyici', 'en' => 'Image viewer'],
    'close'         => ['fa' => 'بستن', 'tr' => 'Kapat', 'en' => 'Close'],
    'previous'      => ['fa' => 'قبلی', 'tr' => 'Önceki', 'en' => 'Previous'],
    'next'          => ['fa' => 'بعدی', 'tr' => 'Sonraki', 'en' => 'Next'],
    'notFoundTitle' => ['fa' => 'صفحه یافت نشد', 'tr' => 'Sayfa bulunamadı', 'en' => 'Page not found'],
    'backHome'      => ['fa' => 'بازگشت به صفحه اصلی', 'tr' => 'Ana sayfaya dön', 'en' => 'Back to homepage'],
    'noProjects'    => ['fa' => 'پروژه‌ای یافت نشد', 'tr' => 'Proje bulunamadı', 'en' => 'No projects found'],
]);

// Image size presets (width => height, 0 for proportional).
// thumb.php only serves these exact sizes.
define('IMAGE_SIZES', [
    'logo'      => ['w' => 128, 'h' => 0],
    'thumbnail' => ['w' => 300, 'h' => 300],
    'card'      => ['w' => 600, 'h' => 0],
    'tile'      => ['w' => 800, 'h' => 0],
    'hero-md'   => ['w' => 900, 'h' => 0],
    'hero'      => ['w' => 1200, 'h' => 0],
    'og'        => ['w' => 1200, 'h' => 630],
]);
