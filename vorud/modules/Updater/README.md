# Updater Module

[![Core Module](https://img.shields.io/badge/Type-Core%20Module-blue.svg)](https://getcockpit.com)

> **Automated system updates and version management for Cockpit CMS**

The Updater module provides a secure, automated way to update Cockpit CMS to the latest version. It handles downloading, verification, backup, and installation of updates while ensuring compatibility and maintaining data integrity.

## ✨ Features

### 🔄 **Automated Updates**
- **One-Click Updates** - Update Cockpit from the admin interface
- **CLI Updates** - Update via command line for automation
- **Version Detection** - Automatically detect available updates
- **Compatibility Checks** - Ensure PHP version compatibility
- **Dry Run Mode** - Simulate updates without making changes

### 🛡️ **Safe Update Process**
- **Pre-Update Validation** - Check system requirements before updating
- **Atomic Updates** - Complete or rollback, no partial updates
- **Cache Clearing** - Automatic cache and opcache clearing
- **Space Support** - Update works across all spaces
- **Comprehensive Logging** - Full audit trail of all update activities

### 📦 **Update Sources**
- **Core Updates** - Update Cockpit core system
- **Pro Updates** - Update Cockpit Pro edition
- **Custom Sources** - Configure custom update servers
- **Version Selection** - Choose specific versions to install

### 🔒 **Security**
- **Super Admin Only** - Restricted to super administrators
- **Master Instance Only** - Updates only from master instance
- **Secure Downloads** - HTTPS downloads with verification
- **Risk Acknowledgment** - Explicit confirmation required

## 🚀 Quick Start

### Check for Updates

Updates are available only to super administrators on the master instance:

```php
// Check if update is available
$latest = $app->helper('updater')->getLatestReleaseInfo();
$updateAvailable = version_compare($latest['version'], APP_VERSION, '>');

if ($updateAvailable) {
    echo "Update available: {$latest['version']}";
    echo "Released: {$latest['date']}";
}
```

### Update via Admin Interface

1. Navigate to **Settings → Updater**
2. Review current and available versions
3. Check the acknowledgment checkbox
4. Click **Update** to begin the process

### Update via CLI

```bash
# Update to latest version (core)
./tower app:update

# Update to latest Pro version
./tower app:update pro

# Update to specific version
./tower app:update core 2.8.0
```

## 📋 Update Process

### 1. **Pre-Update Phase**

```php
// The updater performs these checks:
- Verifies write permissions on APP_DIR
- Downloads update package
- Validates PHP version compatibility
- Creates temporary extraction directory
```

### 2. **Update Phase**

```php
// Core update steps:
- Extract update package
- Preserve config/ directory
- Preserve storage/ directory
- Copy new files to application
- Clean up temporary files
```

### 3. **Post-Update Phase**

```php
// Cleanup and optimization:
- Delete module cache files
- Clear all space caches
- Reset PHP opcache
- Reload application
```

## 🔧 Configuration

### Custom Release Server

Configure a custom update server in `config/config.php`:

```php
return [
    'updater' => [
        'releasesUrl' => 'https://your-server.com/cockpit-releases'
    ]
];
```

### Release Server Structure

Your custom release server should provide:

```
/releases/
├── latest.json          # Latest version info
├── master/
│   ├── cockpit-core.zip
│   └── cockpit-pro.zip
├── 2.8.0/
│   ├── cockpit-core.zip
│   └── cockpit-pro.zip
└── 2.7.0/
    ├── cockpit-core.zip
    └── cockpit-pro.zip
```

### latest.json Format

```json
{
    "version": "2.8.0",
    "date": "2024-01-15",
    "notes": "Bug fixes and performance improvements",
    "php": "^8.0",
    "core": {
        "size": "5.2MB",
        "checksum": "sha256:..."
    },
    "pro": {
        "size": "6.8MB",
        "checksum": "sha256:..."
    }
}
```

## 📊 API Reference

### Helper Methods

#### Get Latest Release Info

```php
$latest = $app->helper('updater')->getLatestReleaseInfo();

// Returns:
[
    'version' => '2.8.0',
    'date' => '2024-01-15',
    'notes' => 'Bug fixes and performance improvements',
    'php' => '^8.0'
]
```

#### Perform Update

```php
// Update to latest version
$app->helper('updater')->update(); // defaults to master/core

// Update to specific version/target
$app->helper('updater')->update('2.8.0', 'pro');

// Parameters:
// $version - Version string or 'master' for latest
// $target - 'core' or 'pro'
```

### Controller Endpoints

```php
// Check for updates (GET)
/updater

// Perform update (POST)
/updater/update
```

## 🖥️ CLI Commands

### Update Command

```bash
# Basic usage
./tower app:update [target] [version] [--dry-run]

# Examples:

# Update core to latest
./tower app:update

# Update pro to latest
./tower app:update pro

# Update core to specific version
./tower app:update core 2.8.0

# Update pro to specific version
./tower app:update pro 2.8.0

# Simulate update without making changes
./tower app:update --dry-run

# Dry run for specific version
./tower app:update pro 2.8.0 --dry-run
```

### Command Arguments

| Argument | Description | Default |
|----------|-------------|---------|
| `target` | Update target: 'core' or 'pro' | 'core' |
| `version` | Version to install or 'master' | 'master' |

### Command Options

| Option | Description |
|--------|-------------|
| `--dry-run` | Simulate the update without making changes. Checks version info, PHP compatibility, write permissions, and temp directory access. |

### Dry Run Output

The `--dry-run` option performs the following checks:

1. **Version Check** - Shows current and target versions
2. **Release Info** - Fetches and displays latest release information
3. **PHP Compatibility** - Verifies PHP version meets requirements
4. **Write Permissions** - Checks if app directory is writable
5. **Temp Directory** - Verifies temp directory is accessible
6. **Update Source** - Shows the download URL that would be used

Example output:
```
[Dry Run] Simulating update process...

Current version: 2.7.0
Target version: master [core]

Checking latest release info...
Latest available: 2.8.0 (2024-01-15)

Checking PHP compatibility...
Current PHP: 8.2.0
Required PHP: >= 8.0

Checking write permissions...
App directory: /var/www/cockpit
Status: Writable

Checking temp directory...
Temp directory: /var/www/cockpit/storage/tmp
Status: Writable

Update source:
URL: https://files.getcockpit.com/releases/master/cockpit-core.zip

--- Summary ---

[✓] Dry run completed successfully. Update should work.
```

## 🔒 Security Considerations

### Access Control

```php
// Updates are restricted to super admins on master instance
if (!$this->helper('acl')->isSuperAdmin() || !$this->helper('spaces')->isMaster()) {
    // Access denied
}
```

### Safe Update Practices

1. **Always backup before updating**
   ```bash
   # Backup database
   ./tower app:backup
   
   # Backup files
   tar -czf cockpit-backup.tar.gz /path/to/cockpit
   ```

2. **Test updates in staging first**
   ```bash
   # Update staging environment
   ./tower app:update
   
   # Verify everything works
   # Then update production
   ```

3. **Monitor the update process**
   ```php
   // Updates are logged
   $app->on('updater.before', function($version, $target) {
       $this->module('system')->log("Starting update to {$version} [{$target}]");
   });
   ```

## 🔧 Advanced Usage

### Custom Update Process

```php
// Extend the updater helper
class CustomUpdater extends \Updater\Helper\Updater {
    
    protected function process(string $zipUrl, string $zipRoot = '/'): bool {
        
        // Pre-update backup
        $this->backupCurrentVersion();
        
        // Call parent update
        $result = parent::process($zipUrl, $zipRoot);
        
        // Post-update tasks
        if ($result) {
            $this->runMigrations();
            $this->notifyAdmins();
        }
        
        return $result;
    }
    
    private function backupCurrentVersion() {
        // Custom backup logic
    }
    
    private function runMigrations() {
        // Run database migrations
    }
    
    private function notifyAdmins() {
        // Send update notifications
    }
}
```

### Automated Updates

```php
// Schedule automatic updates (use with caution!)
$app->on('cron.daily', function() {
    
    $latest = $this->helper('updater')->getLatestReleaseInfo();
    
    if (version_compare($latest['version'], APP_VERSION, '>')) {
        
        // Backup first
        $this->helper('backup')->createBackup();
        
        // Perform update
        try {
            $this->helper('updater')->update();
            $this->module('system')->log('Automatic update completed');
        } catch (\Exception $e) {
            $this->module('system')->log('Automatic update failed: ' . $e->getMessage(), 'error');
        }
    }
});
```

## 🐛 Troubleshooting

### Common Issues

**❌ "App root is not writable" error**
- Check file permissions on Cockpit root directory
- Ensure web server user has write access
- Run: `chmod -R 755 /path/to/cockpit`

**❌ "Couldn't download update" error**
- Check internet connectivity
- Verify releases URL is accessible
- Check PHP `allow_url_fopen` setting
- Review proxy settings if behind firewall

**❌ "PHP version not compatible" error**
- Check current PHP version: `php -v`
- Upgrade PHP to required version
- Update may require newer PHP version

**❌ Update completes but changes don't appear**
- Clear browser cache
- Clear Cockpit cache: `./tower system:cache:flush`
- Restart PHP-FPM or web server
- Check opcache is cleared

### Recovery from Failed Update

If an update fails and leaves the system in an inconsistent state:

```bash
# 1. Restore from backup
cd /path/to/cockpit
rm -rf ./*
tar -xzf /path/to/backup.tar.gz

# 2. Clear all caches
rm -rf storage/cache/*
rm -rf storage/tmp/*

# 3. Reset opcache
# Add to a PHP file and access via browser:
opcache_reset();
```

### Update Logs

The updater module logs all update activities to the `updater` channel. Logged events include:

- **Update initiated** - Who started the update (user or CLI), target version
- **Download started** - URL of the update package
- **Download complete** - Package extracted successfully
- **PHP compatibility check** - Required vs current PHP version
- **Files installed** - Update files copied to application
- **Caches cleared** - Module caches and opcache reset
- **Update complete** - Final success message
- **Update failed** - Error details if something goes wrong

Check update-related logs:

```php
// View update logs
$logs = $app->dataStorage->find('system/logs', [
    'filter' => [
        'channel' => 'updater',
        'timestamp' => ['$gte' => strtotime('-7 days')]
    ],
    'sort' => ['timestamp' => -1]
]);
```

Log context includes:
- `user` / `user_id` - Who initiated the update (web interface only)
- `source` - 'cli' for command-line updates
- `version` - Target version
- `target` - 'core' or 'pro'
- `from_version` - Version before update
- `php_version` - PHP version at time of update
- `error` - Error message (if update failed)

## 📄 License

This is a core module of Cockpit CMS distributed under the MIT license.
