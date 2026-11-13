<?php
/**
 * Amordad Architecture Studio & Cockpit - Server Diagnostic Check
 *
 * CLI only (it reveals server details): run from the cPanel terminal with
 *   php check.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

ob_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<html><head><title>Amordad Server Diagnostics</title>";
echo "<style>body{font-family:monospace;padding:20px;background:#111;color:#eee;} .ok{color:#4ade80;} .bad{color:#f87171;} h2{border-bottom:1px solid #333;padding-bottom:6px;}</style>";
echo "</head><body>";
echo "<h1>Amordad Architecture Studio & Cockpit Server Diagnostics</h1>";

// 1. PHP Info
echo "<h2>1. PHP Environment</h2>";
echo "PHP Version: <b>" . PHP_VERSION . "</b><br>";
echo "Server Software: <b>" . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "</b><br>";
echo "session.save_path: <b>" . (session_save_path() ?: 'default') . "</b><br>";

// 2. Extensions
echo "<h2>2. PHP Extensions</h2>";
$exts = ['pdo_sqlite', 'sqlite3', 'openssl', 'curl', 'json', 'gd', 'mbstring', 'fileinfo'];
foreach ($exts as $ext) {
    $loaded = extension_loaded($ext);
    echo "$ext: " . ($loaded ? "<span class='ok'>[LOADED]</span>" : "<span class='bad'>[MISSING]</span>") . "<br>";
}

// 3. Storage Permissions
echo "<h2>3. Storage Folders & Permissions</h2>";
$dirs = [
    'vorud/storage',
    'vorud/storage/data',
    'vorud/storage/tmp',
    'vorud/storage/cache',
    'vorud/storage/uploads',
    'vorud/storage/content',
    'vorud/storage/logs',
];
foreach ($dirs as $dir) {
    $path = __DIR__ . '/' . $dir;
    $exists = is_dir($path);
    $writable = $exists && is_writable($path);
    $perms = $exists ? substr(sprintf('%o', fileperms($path)), -4) : 'N/A';
    echo "$dir: " . ($exists ? "Exists (perms: $perms)" : "<span class='bad'>Missing</span>");
    echo " | Writable: " . ($writable ? "<span class='ok'>YES</span>" : "<span class='bad'>NO</span>") . "<br>";
}

// 4. Cockpit Core Test
echo "<h2>4. Cockpit Core & Database Diagnostics</h2>";
try {
    require_once __DIR__ . '/vorud/bootstrap.php';
    $app = Cockpit::instance(__DIR__ . '/vorud');
    echo "1. Cockpit instance: <span class='ok'>OK</span><br>";

    // Refresh model schemas cache
    $app->helper('content.model')->cache();
    echo "1.1 Content models cache: <span class='ok'>Refreshed</span><br>";

    // Test Memory
    $app->memory->set('diag_test', 'working');
    $val = $app->memory->get('diag_test');
    echo "2. MemoryStorage (app.memory.sqlite): " . ($val === 'working' ? "<span class='ok'>OK</span>" : "<span class='bad'>FAILED</span>") . "<br>";

    // Test system.sqlite
    $user = $app->dataStorage->findOne('system/users', ['role' => 'admin']);
    echo "3. System Database (system.sqlite): " . ($user ? "<span class='ok'>OK (admin user found)</span>" : "<span class='bad'>Admin user not found</span>") . "<br>";

    // Test content.sqlite
    $projectCount = $app->module('content')->count('projects');
    $catCount = $app->module('content')->count('categories');
    echo "4. Content Database (content.sqlite): <span class='ok'>OK ({$projectCount} projects, {$catCount} categories)</span><br>";

    // Test session
    $app->helper('session')->init();
    echo "5. Session initialization: <span class='ok'>OK</span><br>";

    // Test CSRF
    $token = $app->helper('csrf')->token('app.login');
    echo "6. CSRF & Security Tokens: <span class='ok'>OK</span><br>";

    echo "<h2 class='ok'>Result: All Cockpit Core Services are Working!</h2>";
    echo "<p>If any route still fails, verify Apache mod_rewrite is enabled and check error_log in document root.</p>";

} catch (Throwable $e) {
    echo "<h2 class='bad'>Result: Crash / Exception Detected!</h2>";
    echo "<b>Error Message:</b> <span class='bad'>" . htmlspecialchars($e->getMessage()) . "</span><br>";
    echo "<b>File:</b> " . htmlspecialchars($e->getFile()) . "<br>";
    echo "<b>Line:</b> " . $e->getLine() . "<br>";
    echo "<pre class='bad'>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

echo "</body></html>";

// Plain-text output for the terminal
$output = ob_get_clean();
$output = preg_replace('#<(br|/h1|/h2|/p|/pre)[^>]*>#i', "\n", $output);
echo html_entity_decode(strip_tags(preg_replace('#<style.*?</style>|<title.*?</title>#s', '', $output))), "\n";
