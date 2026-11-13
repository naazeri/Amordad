<?php

function generateRandomCockpitPassword($length = 12) {
    $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!@#$%&*';
    $password = '';
    for ($i = 0; $i < $length; $i++) $password .= $chars[random_int(0, strlen($chars) - 1)];
    return $password;
}

function hasSQLiteSupport() {
    try {

        if (extension_loaded('pdo')) {
            $test = new PDO('sqlite::memory:');
            return true;
        }

    } catch (Exception $e) {}

    return false;
}

function ensureWritableStorageFolder($path) {
    try {
        $dir = __DIR__.'/../storage'.$path;
        if (!file_exists($dir)) {
            mkdir($dir, 0700, true);
            if ($path === '/data') {
                if (file_put_contents($dir.'/.htaccess', 'deny from all') === false) {
                    return false;
                }
            }
        }
        return is_writable($dir);
    } catch (Exception $e) {
        error_log($e);
        return false;
    }
}

// misc checks
$checks = [
    'Php version >= 8.2.0'                               => (version_compare(PHP_VERSION, '8.2.0') >= 0),
    'Missing PDO extension with Sqlite support'          => hasSQLiteSupport(),
    'Curl extension not available'                       => extension_loaded('curl'),
    'Fileinfo extension not available'                   => extension_loaded('fileinfo'),
    'GD extension not available'                         => extension_loaded('gd'),
    'OpenSSL extension not available'                    => extension_loaded('openssl'),
    'Data folder is not writable: /storage/data'         => ensureWritableStorageFolder('/data'),
    'Cache folder is not writable: /storage/cache'       => ensureWritableStorageFolder('/cache'),
    'Temp folder is not writable: /storage/tmp'          => ensureWritableStorageFolder('/tmp'),
    'Thumbs folder is not writable: /storage/tmp/thumbs' => ensureWritableStorageFolder('/tmp/thumbs'),
    'Uploads folder is not writable: /storage/uploads'   => ensureWritableStorageFolder('/uploads'),
];

$failed = [];

foreach ($checks as $info => $check) {
    if (!$check) $failed[] = $info;
}

$APP_SPACE_DIR = dirname(__DIR__);
$APP_SPACE = null;

// support ?space=myenv to install custom cockpit instance from /.spaces/*
if (isset($_GET['space']) && $_GET['space']) {

    $APP_SPACE = preg_replace('/[^a-z0-9\-_]/', '', $_GET['space']);
    $spaceDir  = $APP_SPACE_DIR."/.spaces/{$APP_SPACE}";

    if (!file_exists($spaceDir)) {
        $failed[] = "Space :{$APP_SPACE}: does not exist!";
    } else {
        $APP_SPACE_DIR = $spaceDir;
    }
}

$showForm = false;
$installed = false;
$formValues = [
    'user' => 'admin',
    'name' => 'Admin',
    'email' => '',
    'password' => '',
];

if (!count($failed)) {

    if (!class_exists('Cockpit')) {
        include (__DIR__.'/../bootstrap.php');
    }

    $app = Cockpit::instance($APP_SPACE_DIR, [
        'app_space' => $APP_SPACE
    ]);

    try {

        // check memory config
        @$app->memory->get('test');

        if ($app->dataStorage->getCollection('system/users')->count()) {

            header('Location: ../'.($APP_SPACE ? ":{$APP_SPACE}" : ""));
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            $formValues['user']  = trim((string)($_POST['user'] ?? 'admin'));
            $formValues['name']  = trim((string)($_POST['name'] ?? 'Admin'));
            $formValues['email'] = trim((string)($_POST['email'] ?? ''));
            $formValues['password'] = (string)($_POST['password'] ?? '');

            $formErrors = [];

            if (!preg_match('/^[A-Za-z0-9_.\-]{2,60}$/', $formValues['user'])) {
                $formErrors[] = 'Username must be 2-60 characters (letters, digits, . _ -)';
            }

            if ($formValues['name'] === '' || mb_strlen($formValues['name']) > 120) {
                $formErrors[] = 'Display name is required (max 120 characters)';
            }

            if (!filter_var($formValues['email'], FILTER_VALIDATE_EMAIL)) {
                $formErrors[] = 'A valid email address is required';
            }

            if (strlen($formValues['password']) < 8) {
                $formErrors[] = 'Password must be at least 8 characters';
            }

            if (count($formErrors)) {

                $showForm = true;
                $failed = $formErrors;

            } else {

                $created = time();

                $user = [
                    'active' => true,
                    'user' => $formValues['user'],
                    'name' => $formValues['name'],
                    'email' => $formValues['email'],
                    'password' => $app->hash($formValues['password']),
                    'i18n' => 'en',
                    'role' => 'admin',
                    'theme' => 'auto',
                    '_modified' => $created,
                    '_created' => $created
                ];

                $app->dataStorage->save('system/users', $user);
                $app->trigger('app.system.install');

                $installed = true;
            }

        } else {

            // first visit — pre-fill with a suggested strong password the user can keep or replace
            $formValues['password'] = generateRandomCockpitPassword();
            $showForm = true;
        }

    } catch(Throwable $e) {

        if (str_contains(get_class($e), 'MongoDB')) {
            $failed[] = 'MongoDB connection failed';
        } else {
            $failed[] = $e->getMessage();
        }

        $showForm = false;
    }

}

$formAction = '?'.($APP_SPACE ? "space={$APP_SPACE}" : '');

?><!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>System installation</title>
    <link rel="icon" type="image/png" href="../favicon.png">
    <link href="../modules/App/assets/css/app.css" type="text/css" rel="stylesheet">
    <script src="../modules/App/assets/vendor/kiss/lib.js" type="module"></script>
    <script src="../modules/App/assets/components/bg-fluxanimation/bg-fluxanimation.js" type="module"></script>
    <style>

        .install-card {
            box-shadow: 0 2.8px 2.2px rgba(0, 0, 0, 0.034), 0 6.7px 5.3px rgba(0, 0, 0, 0.048), 0 12.5px 10px rgba(0, 0, 0, 0.06), 0 22.3px 17.9px rgba(0, 0, 0, 0.072), 0 41.8px 33.4px rgba(0, 0, 0, 0.086), 0 100px 80px rgba(0, 0, 0, 0.12);
            border-radius: 4px;
            overflow: hidden;
            width: 450px;
        }
    </style>
    <script>
        document.documentElement.setAttribute('data-theme', getComputedStyle(document.documentElement).getPropertyValue("--app-auto-theme").trim());
    </script>
</head>
<body class="kiss-flex kiss-flex-center kiss-flex-middle">

    <bg-fluxanimation class="kiss-cover" colors="<?= ((count($failed) && !$showForm) ? '--kiss-color-danger' : '--kiss-color-primary') ?>"></bg-fluxanimation>


    <kiss-container class="kiss-position-relative install-container">

        <kiss-card class="install-card kiss-padding-large animated <?=((count($failed) && !$showForm) ? 'bounceInDown':'pulse')?>" theme="shadowed contrast">

            <div>
                <div class="kiss-flex kiss-margin">
                    <div class="kiss-margin-small-right">
                        <img src="../modules/App/assets/img/logo.svg" width="35" height="35"alt="logo">
                    </div>
                    <div class="kiss-flex-1">
                        <strong>Cockpit</strong>
                        <div class="kiss-color-muted kiss-size-xsmall">Content Platform</div>
                    </div>
                </div>

                <hr class="kiss-margin-large-bottom">

                <?php if (count($failed) && !$showForm): ?>

                    <h1 class="kiss-size-3">
                        <icon class="kiss-color-danger kiss-margin-small-right" size="larger">block</icon>
                        Installation failed
                    </h1>

                    <kiss-card class="kiss-padding kiss-bgcolor-contrast kiss-flex kiss-flex-column kiss-margin">

                        <div class="kiss-text-caption kiss-text-bold kiss-color-muted kiss-margin-small">Reasons:</div>

                        <?php foreach ($failed as $info): ?>
                        <div class="kiss-text-monospace kiss-margin-small kiss-flex">
                            <span>🔥</span><div class="kiss-flex-1 kiss-size-small kiss-margin-small-left"><?=htmlspecialchars($info, ENT_QUOTES, 'UTF-8')?></div>
                        </div>
                        <?php endforeach; ?>

                    </kiss-card>

                    <div class="kiss-margin-large-bottom kiss-color-muted">
                        Please try to fix the errors and retry the installation.
                    </div>

                    <div class="kiss-margin-large">
                        <a class="kiss-button kiss-width-1-1" href="?<?=implode('&', [time(), ($APP_SPACE ? "space={$APP_SPACE}" : "")])?>">Retry installation</a>
                    </div>

                <?php elseif ($showForm): ?>

                    <h1 class="kiss-size-3">
                        <icon class="kiss-color-primary kiss-margin-small-right" size="larger">person_add</icon>
                        Create admin account
                    </h1>

                    <div class="kiss-margin-bottom kiss-color-muted kiss-size-small">
                        Set the credentials for your first admin user. You can change them later.
                    </div>

                    <?php if (count($failed)): ?>
                    <kiss-card class="kiss-padding kiss-bgcolor-contrast kiss-flex kiss-flex-column kiss-margin">
                        <?php foreach ($failed as $info): ?>
                        <div class="kiss-text-monospace kiss-margin-small kiss-flex">
                            <span>🔥</span><div class="kiss-flex-1 kiss-size-small kiss-margin-small-left"><?=htmlspecialchars($info, ENT_QUOTES, 'UTF-8')?></div>
                        </div>
                        <?php endforeach; ?>
                    </kiss-card>
                    <?php endif; ?>

                    <form method="post" action="<?=htmlspecialchars($formAction, ENT_QUOTES, 'UTF-8')?>">

                        <div class="kiss-margin">
                            <label class="kiss-text-caption kiss-color-muted">Username</label>
                            <input class="kiss-input kiss-width-1-1" type="text" name="user" value="<?=htmlspecialchars($formValues['user'], ENT_QUOTES, 'UTF-8')?>" autocomplete="off" required>
                        </div>

                        <div class="kiss-margin">
                            <label class="kiss-text-caption kiss-color-muted">Display name</label>
                            <input class="kiss-input kiss-width-1-1" type="text" name="name" value="<?=htmlspecialchars($formValues['name'], ENT_QUOTES, 'UTF-8')?>" autocomplete="off" required>
                        </div>

                        <div class="kiss-margin">
                            <label class="kiss-text-caption kiss-color-muted">Email</label>
                            <input class="kiss-input kiss-width-1-1" type="email" name="email" value="<?=htmlspecialchars($formValues['email'], ENT_QUOTES, 'UTF-8')?>" autocomplete="off" required autofocus>
                        </div>

                        <div class="kiss-margin">
                            <label class="kiss-text-caption kiss-color-muted">Password <span class="kiss-color-muted">(min 8 characters)</span></label>
                            <input class="kiss-input kiss-width-1-1 kiss-text-monospace" type="text" name="password" value="<?=htmlspecialchars($formValues['password'], ENT_QUOTES, 'UTF-8')?>" autocomplete="off" minlength="8" required>
                        </div>

                        <div class="kiss-margin-large">
                            <button type="submit" class="kiss-button kiss-button-primary kiss-width-1-1">Install Cockpit</button>
                        </div>

                    </form>

                <?php else: ?>

                    <h1 class="kiss-size-3">
                        <icon class="kiss-color-success kiss-margin-small-right" size="larger">verified</icon>
                        Installation completed
                    </h1>

                    <div class="kiss-text-caption kiss-text-bold kiss-color-muted kiss-margin">Next step:</div>

                    <div class="kiss-margin">Please login into Cockpit using the credentials you just set.</div>

                    <kiss-card class="kiss-text-monospace kiss-padding kiss-bgcolor-contrast kiss-flex kiss-flex-column kiss-margin">
                        <div><icon class="kiss-color-muted kiss-margin-right" size="larger">person</icon><?=htmlspecialchars($formValues['user'], ENT_QUOTES, 'UTF-8')?></div>
                        <div><icon class="kiss-color-muted kiss-margin-right" size="larger">mail</icon><?=htmlspecialchars($formValues['email'], ENT_QUOTES, 'UTF-8')?></div>
                    </kiss-card>

                    <div class="kiss-margin-large-bottom kiss-color-muted">
                        For security, remove or protect the <code>install/</code> directory now that setup is complete.
                    </div>

                    <div class="kiss-margin-large">
                        <a class="kiss-button kiss-button-primary kiss-width-1-1" href="../<?=($APP_SPACE ? ":{$APP_SPACE}" : "")?>">Login now</a>
                    </div>

                <?php endif; ?>

            </div>

        </kiss-card>

    </kiss-container>

</body>
</html>
