<?php
/**
 * Root index — redirect to the visitor's preferred locale (Accept-Language),
 * falling back to the default locale, and keep the query string.
 */
require_once __DIR__ . '/includes/config.php';

$target = DEFAULT_LOCALE;
$accept = strtolower((string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''));
$best = 0.0;
foreach (explode(',', $accept) as $part) {
    $pieces = explode(';', trim($part));
    $lang = substr(trim($pieces[0]), 0, 2);
    $q = 1.0;
    if (isset($pieces[1]) && preg_match('/q=([0-9.]+)/', $pieces[1], $m)) {
        $q = (float)$m[1];
    }
    if (in_array($lang, LOCALES, true) && $q > $best) {
        $best = $q;
        $target = $lang;
    }
}

$query = $_SERVER['QUERY_STRING'] ?? '';
header('Vary: Accept-Language');
header('Location: /' . $target . '/' . ($query !== '' ? '?' . $query : ''), true, 302);
exit;
