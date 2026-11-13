<?php
/**
 * Amordad — Shared Helper Functions
 *
 * Cockpit CMS v2 PHP API integration.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/richtext.php';
require_once __DIR__ . '/images.php';

// Bootstrap Cockpit CMS
if (!defined('APP_CLI')) define('APP_CLI', false);
if (!defined('APP_ADMIN')) define('APP_ADMIN', false);
require_once COCKPIT_DIR . '/bootstrap.php';

/**
 * Get a localized field value with fallback to Persian.
 *
 * Fields use the pattern: {field}_{locale} → e.g. title_fa, title_en
 *
 * @param array  $item   Content item
 * @param string $field  Base field name (without locale suffix)
 * @param string $locale Current locale code
 * @return string
 */
function localized(array $item, string $field, string $locale): string {
    // Try requested locale
    $value = $item["{$field}_{$locale}"] ?? '';
    if (!empty(trim((string)$value))) return (string)$value;
    // Fallback to Persian (default)
    $value = $item["{$field}_fa"] ?? '';
    if (!empty(trim((string)$value))) return (string)$value;
    // Fallback to any locale
    foreach (LOCALES as $loc) {
        $value = $item["{$field}_{$loc}"] ?? '';
        if (!empty(trim((string)$value))) return (string)$value;
    }
    return '';
}

/**
 * Get a localized field value WITHOUT falling back to another language.
 * Used for meta tags, where text in the wrong language is worse than none.
 */
function localizedStrict(array $item, string $field, string $locale): string {
    $value = $item["{$field}_{$locale}"] ?? '';
    return is_string($value) ? trim($value) : '';
}

/**
 * Get an interface string (see UI_STRINGS) for a locale.
 */
function ui(string $key, string $locale): string {
    return UI_STRINGS[$key][$locale] ?? UI_STRINGS[$key]['en'] ?? $key;
}

/**
 * Plain-text excerpt of rich text, cut at a word boundary (for meta descriptions).
 */
function textExcerpt(string $html, int $maxLength = 155): string {
    $text = html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], ' ', $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = trim(preg_replace('/\s+/u', ' ', $text));
    if (mb_strlen($text) <= $maxLength) return $text;
    $cut = mb_substr($text, 0, $maxLength);
    $lastSpace = mb_strrpos($cut, ' ');
    if ($lastSpace !== false && $lastSpace > $maxLength * 0.6) {
        $cut = mb_substr($cut, 0, $lastSpace);
    }
    return rtrim($cut, " ,.;:،؛") . '…';
}

/**
 * Cache-busting URL for a local static asset (e.g. /assets/css/styles.css).
 */
function assetUrl(string $path): string {
    $file = dirname(__DIR__) . $path;
    return is_file($file) ? $path . '?v=' . filemtime($file) : $path;
}

/**
 * Get current locale from GET parameter, validated.
 */
function getCurrentLocale(): string {
    $locale = $_GET['locale'] ?? DEFAULT_LOCALE;
    return is_string($locale) && in_array($locale, LOCALES, true) ? $locale : DEFAULT_LOCALE;
}

/**
 * Check if locale is RTL.
 */
function isRtl(string $locale): bool {
    return in_array($locale, RTL_LOCALES, true);
}

/**
 * Get text direction for a locale.
 */
function getDirection(string $locale): string {
    return isRtl($locale) ? 'rtl' : 'ltr';
}

/**
 * Safely escape output for HTML.
 */
function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// ────────────── URL Helpers ──────────────

function localePath(string $locale, string $path = ''): string {
    $path = ltrim($path, '/');
    return "/{$locale}" . ($path ? "/{$path}" : '/');
}

function categoryPath(string $locale, string $slug): string {
    return localePath($locale, "category/{$slug}");
}

function projectPath(string $locale, string $slug): string {
    return localePath($locale, "project/{$slug}");
}

function switchLocalePath(string $currentLocale, string $targetLocale, string $path): string {
    return preg_replace(
        '#^/' . preg_quote($currentLocale, '#') . '(/|$)#',
        "/{$targetLocale}$1",
        $path
    );
}

function currentUrl(): string {
    return $_SERVER['REQUEST_URI'] ?? '/';
}

// ────────────── Asset / Media Helpers ──────────────

/**
 * Get the URL for a Cockpit asset (image).
 *
 * In Cockpit v2, an asset field stores an array like:
 * { "_id": "...", "path": "...", "title": "...", "mime": "image/jpeg", ... }
 *
 * For thumbnails, we use the Cockpit thumbnail API endpoint.
 *
 * @param mixed $asset  The asset array from Cockpit
 * @param string $size  Size preset name (from IMAGE_SIZES constant)
 * @return string URL or empty string
 */
function mediaUrl($asset, string $size = ''): string {
    if (empty($asset)) return '';

    if (is_string($asset)) {
        $assetPath = $asset;
    } elseif (is_array($asset)) {
        $assetPath = $asset['path'] ?? '';
    } else {
        return '';
    }

    if (empty($assetPath)) return '';

    $cleanPath = '/' . ltrim($assetPath, '/');

    // If a size preset is specified and exists:
    if ($size && isset(IMAGE_SIZES[$size])) {
        $params = IMAGE_SIZES[$size];
        // For proportional resizes of resizable Cockpit assets, use responsive WebP variants
        if (($params['h'] ?? 0) === 0 && is_array($asset) && !empty($asset['_id']) && img_optimization_enabled() && img_is_resizable($asset) && in_array($params['w'], IMG_ALLOWED_WIDTHS, true)) {
            return img_variant_url($asset, $params['w']);
        }

        $query = http_build_query([
            'src' => $cleanPath,
            'w'   => $params['w'],
            'h'   => $params['h'] ?: 0,
        ]);
        return '/thumb.php?' . $query;
    }

    // Direct upload path
    return '/vorud/storage/uploads' . $cleanPath;
}

/**
 * Build a srcset attribute value from size presets (e.g. ['card', 'hero-md', 'hero']).
 */
function mediaSrcset($asset, array $sizes): string {
    $parts = [];
    foreach ($sizes as $size) {
        $url = mediaUrl($asset, $size);
        if ($url && isset(IMAGE_SIZES[$size])) {
            $parts[] = $url . ' ' . IMAGE_SIZES[$size]['w'] . 'w';
        }
    }
    return implode(', ', $parts);
}

/**
 * Intrinsic width/height of an asset scaled to a preset width, for CLS-free
 * width/height attributes. Falls back to the given ratio when unknown.
 */
function mediaDimensions($asset, int $width, float $fallbackRatio = 0.75): array {
    $origW = is_array($asset) ? (int)($asset['width'] ?? 0) : 0;
    $origH = is_array($asset) ? (int)($asset['height'] ?? 0) : 0;
    if ($origW > 0 && $origH > 0) {
        $w = min($width, $origW);
        return [$w, (int)round($w * $origH / $origW)];
    }
    return [$width, (int)round($width * $fallbackRatio)];
}

/**
 * Get alt text for a media item.
 *
 * Asset titles are often file names or generic words ("عکس", "logo"), and are
 * usually Persian; in those cases the contextual fallback is used instead.
 */
function mediaAlt($asset, string $fallback = '', ?string $locale = null): string {
    if (empty($asset) || !is_array($asset)) return e($fallback);

    $generic = ['عکس', 'تصویر', 'لوگو', 'photo', 'image', 'img', 'logo', 'picture'];
    foreach (['title', 'description'] as $key) {
        $value = trim((string)($asset[$key] ?? ''));
        if ($value === '') continue;
        if (preg_match('/\.(jpe?g|png|webp|gif|svg)$/i', $value) || str_contains($value, '_uid_')) continue;
        if (in_array(mb_strtolower($value), $generic, true)) continue;
        if ($locale !== null && $locale !== 'fa' && preg_match('/\p{Arabic}/u', $value) && $fallback !== '') continue;
        return e($value);
    }
    return e($fallback);
}

// ────────────── Cockpit Data Access ──────────────

/**
 * Get site settings singleton.
 */
function getSiteSettings(): array {
    $app = Cockpit::instance();
    $data = $app->module('content')->item('siteSettings');
    return $data ?? [];
}

/**
 * Get home page singleton.
 */
function getHomePage(): array {
    $app = Cockpit::instance();
    $data = $app->module('content')->item('homePage', [], null, ['populate' => 2]);
    return $data ?? [];
}

/**
 * Get navigation categories (sorted, filtered by showInNavigation).
 */
function getNavigationCategories(): array {
    $app = Cockpit::instance();
    $items = $app->module('content')->items('categories', [
        'filter' => ['showInNavigation' => true, '_state' => 1],
        'sort'   => ['navigationOrder' => 1],
        'limit'  => 100,
    ]);
    return $items ?? [];
}

/**
 * Get a single category by slug.
 */
function getCategoryBySlug(string $slug): ?array {
    $app = Cockpit::instance();
    $item = $app->module('content')->item('categories', ['slug' => $slug, '_state' => 1]);
    return $item ?: null;
}

/**
 * Get all published projects for a category.
 *
 * A project is public only when its Cockpit state is Published (_state = 1).
 */
function getProjectsByCategory(string $categoryId): array {
    $app = Cockpit::instance();

    // Only the fields the category grid renders; asset fields are stored inline,
    // so no populate is needed.
    $items = $app->module('content')->items('projects', [
        'filter' => ['_state' => 1],
        'fields' => ['title_fa' => 1, 'title_en' => 1, 'title_tr' => 1, 'slug' => 1,
                     'featuredImage' => 1, 'projectGallery' => 1, 'categories' => 1, '_created' => 1],
        'sort'   => ['_created' => -1],
    ]);

    // Filter by category — categories is a link field or array of links
    $filtered = [];
    foreach ($items as $item) {
        $cats = $item['categories'] ?? [];
        if (!is_array($cats) || isset($cats['_id'])) $cats = [$cats];
        foreach ($cats as $cat) {
            $catId = is_array($cat) ? ($cat['_id'] ?? '') : $cat;
            if ($catId === $categoryId) {
                $filtered[] = $item;
                break;
            }
        }
    }

    // Sort projects by newest first (_created descending, fallback to slug/id descending)
    usort($filtered, function ($a, $b) {
        $tA = (int)($a['_created'] ?? 0);
        $tB = (int)($b['_created'] ?? 0);
        if ($tA === $tB) {
            return strcmp((string)($b['slug'] ?? ''), (string)($a['slug'] ?? ''));
        }
        return $tB <=> $tA;
    });

    return $filtered;
}

/**
 * Card image for a project: the featured image, else the first gallery image.
 */
function projectCardImage(array $project) {
    if (!empty($project['featuredImage'])) return $project['featuredImage'];
    foreach ((array)($project['projectGallery'] ?? []) as $gi) {
        $img = is_array($gi) && isset($gi['image']) ? $gi['image'] : $gi;
        if (!empty($img)) return $img;
    }
    return null;
}

/**
 * Get a single project by slug.
 */
function getProjectBySlug(string $slug): ?array {
    $app = Cockpit::instance();
    $item = $app->module('content')->item('projects', ['slug' => $slug], null, ['populate' => 2]);

    if (!$item) return null;

    // Only show published projects (Cockpit state)
    if ((int)($item['_state'] ?? 0) !== 1) {
        return null;
    }

    return $item;
}

// ────────────── Font Helpers ──────────────

/**
 * Build the font CSS variable value (Vazirmatn is loaded locally for all locales).
 */
function getFontStack(): string {
    return "'Vazirmatn', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif";
}

// ────────────── SEO Helpers ──────────────

/**
 * Output common meta tags.
 */
function renderMetaTags(array $meta): void {
    $title = $meta['title'] ?? 'Amordad';
    $description = $meta['description'] ?? '';
    $ogImage = $meta['ogImage'] ?? '';
    $url = $meta['url'] ?? '';
    $locale = $meta['locale'] ?? DEFAULT_LOCALE;
    $currentPath = $meta['path'] ?? '';
    $ogType = $meta['type'] ?? 'website';
    $siteName = $meta['siteName'] ?? '';

    echo '<title>' . e($title) . '</title>' . "\n";
    if ($description) {
        echo '<meta name="description" content="' . e($description) . '">' . "\n";
    }
    echo '<meta property="og:title" content="' . e($title) . '">' . "\n";
    echo '<meta property="og:type" content="' . e($ogType) . '">' . "\n";
    if ($siteName) {
        echo '<meta property="og:site_name" content="' . e($siteName) . '">' . "\n";
    }
    if ($description) {
        echo '<meta property="og:description" content="' . e($description) . '">' . "\n";
    }
    if ($url) {
        echo '<meta property="og:url" content="' . e($url) . '">' . "\n";
        echo '<link rel="canonical" href="' . e($url) . '">' . "\n";
    }
    if ($ogImage) {
        echo '<meta property="og:image" content="' . e($ogImage) . '">' . "\n";
    }
    echo '<meta name="twitter:card" content="' . ($ogImage ? 'summary_large_image' : 'summary') . '">' . "\n";
    echo '<meta property="og:locale" content="' . e(OG_LOCALES[$locale] ?? 'fa_IR') . '">' . "\n";
    foreach (LOCALES as $loc) {
        if ($loc !== $locale) {
            echo '<meta property="og:locale:alternate" content="' . e(OG_LOCALES[$loc]) . '">' . "\n";
        }
    }

    // Hreflang alternate links
    foreach (LOCALES as $loc) {
        $altUrl = SITE_URL . switchLocalePath($locale, $loc, $currentPath);
        echo '<link rel="alternate" hreflang="' . e($loc) . '" href="' . e($altUrl) . '">' . "\n";
    }
    $defaultUrl = SITE_URL . switchLocalePath($locale, DEFAULT_LOCALE, $currentPath);
    echo '<link rel="alternate" hreflang="x-default" href="' . e($defaultUrl) . '">' . "\n";
}

/**
 * Output an Organization JSON-LD block.
 */
function renderOrganizationJsonLd(array $settings, string $locale): void {
    $data = [
        '@context' => 'https://schema.org',
        '@type'    => 'Organization',
        'name'     => localized($settings, 'siteName', $locale) ?: 'Amordad',
        'url'      => SITE_URL . localePath($locale),
    ];
    $logo = $settings['headerLogo'] ?? null;
    if ($logo && ($logoUrl = mediaUrl($logo, 'thumbnail'))) {
        $data['logo'] = SITE_URL . $logoUrl;
    }
    echo '<script type="application/ld+json">'
        . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP)
        . '</script>' . "\n";
}

/**
 * Format copyright text with automatically updating year.
 * Supports {year} for Gregorian year, {jyear} for Solar Hijri year.
 * If neither is present, automatically prefixes © {year}.
 */
function formatCopyright(string $text, string $locale): string {
    $text = trim($text);
    if ($text === '') {
        $text = $locale === 'fa' ? 'کلیه حقوق محفوظ است.' : ($locale === 'tr' ? 'Tüm hakları saklıdır.' : 'All rights reserved.');
    }

    // Fix Persian typo if present (کلیهی -> کلیه)
    $text = str_replace('کلیهی', 'کلیه', $text);

    $year = date('Y');

    // Calculate Solar Hijri year if needed
    $jyear = $year;
    if (class_exists('IntlDateFormatter')) {
        try {
            $fmt = new IntlDateFormatter('fa_IR@calendar=persian', IntlDateFormatter::FULL, IntlDateFormatter::NONE, 'Asia/Tehran', IntlDateFormatter::TRADITIONAL, 'yyyy');
            $jyear = $fmt->format(time());
        } catch (\Throwable $e) {}
    }

    // Replace placeholders if present
    if (strpos($text, '{year}') !== false || strpos($text, '{jyear}') !== false) {
        return str_replace(['{year}', '{jyear}'], [$year, $jyear], $text);
    }

    // If text already starts with © or contains a 4-digit year, return it
    if (strpos($text, '©') !== false || preg_match('/\b(20\d\d|14\d\d)\b/', $text)) {
        return $text;
    }

    // Automatically prefix copyright symbol and current year
    return "© {$year} {$text}";
}
