<?php
/**
 * Cockpit CMS Addon: AdminRtl
 *
 * Automatically ensures Persian (_fa) input fields in the Cockpit Admin panel
 * are rendered Right-to-Left (RTL), while English (_en), Turkish (_tr), and
 * system fields remain strictly Left-to-Right (LTR).
 */

$this->on('app.layout.assets', function(array &$assets, $context) {
    if ($context === 'app:footer') {
        $assets[] = [
            'src' => $this->pathToUrl(__DIR__ . '/assets/admin-rtl.js'),
            'type' => 'module'
        ];
    }
});
