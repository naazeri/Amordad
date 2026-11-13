<?php
/**
 * Cockpit CMS Addon: Hardening
 *
 * Project-specific security guards that would otherwise require patching core:
 * - Login throttling: max LOGIN_MAX_ATTEMPTS attempts per IP per LOGIN_WINDOW
 *   seconds on /auth/check and /auth/validate2FA (reset on successful login).
 * - /auth/validate2FA only accepts users that actually have 2FA enabled, so a
 *   forged 2FA token cannot be combined with a dormant TOTP secret.
 */

const HARDENING_LOGIN_MAX_ATTEMPTS = 10;
const HARDENING_LOGIN_WINDOW = 900; // 15 minutes

/**
 * Stop the current request from a 'before' handler without dispatching the route.
 */
function hardeningReject($app, int $status, string $error): void {
    $app->response->mime = 'json';
    $app->response->status = $status;
    $app->response->body = json_encode(['error' => $error]);
    $app->request->stopped = true;
}

function hardeningThrottleKey($app): string {
    // REMOTE_ADDR only: X-Forwarded-For is client-controlled
    return 'hardening.login.' . sha1((string)($app->request->server['REMOTE_ADDR'] ?? 'unknown'));
}

$this->on('before', function() {

    $route = rtrim($this->request->route ?? '', '/');

    if ($route !== '/auth/check' && $route !== '/auth/validate2FA') {
        return;
    }

    // ── Login throttling ──
    $key = hardeningThrottleKey($this);
    $now = time();
    $entry = $this->memory->get($key, null);

    if (!is_array($entry) || ($now - (int)($entry['start'] ?? 0)) > HARDENING_LOGIN_WINDOW) {
        $entry = ['count' => 0, 'start' => $now];
    }

    if ($entry['count'] >= HARDENING_LOGIN_MAX_ATTEMPTS) {
        $this->response->headers['Retry-After'] = (string)(HARDENING_LOGIN_WINDOW - ($now - $entry['start']));
        hardeningReject($this, 429, 'Too many login attempts. Please try again later.');
        return;
    }

    $entry['count']++;
    $this->memory->set($key, $entry);

    // ── 2FA: only for users that have 2FA enabled ──
    if ($route === '/auth/validate2FA') {
        $token = $this->param('token', null);
        $userId = null;

        if (is_string($token) && $token !== '') {
            try {
                $payload = (array) $this->helper('jwt')->decode($token);
                $userId = $payload['_id'] ?? null;
            } catch (\Throwable $e) {
                $userId = null;
            }
        }

        $user = is_string($userId) ? $this->dataStorage->findOne('system/users', ['_id' => $userId]) : null;

        if (!$user || empty($user['twofa']['enabled'])) {
            hardeningReject($this, 412, 'Precondition Failed');
        }
    }
});

// Successful login: clear the attempt counter for this IP
$this->on('app.auth.setuser', function($user, $permanent) {
    if ($permanent) {
        $this->memory->del(hardeningThrottleKey($this));
    }
});
