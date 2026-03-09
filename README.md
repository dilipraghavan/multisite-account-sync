# Multisite Central Account Sync

**Version:** 1.0.0  
**Requires:** WordPress 6.0+, PHP 8.0+, Multisite enabled  
**License:** GPL-2.0+

## Overview

A WordPress multisite plugin that synchronizes user accounts, roles, and capabilities across all network subsites automatically. Includes a central network admin panel, full audit log, and developer hooks throughout the sync lifecycle.

## Features

- Automatic user sync across all subsites on profile update, role change, or registration
- Network Admin Panel with manual sync trigger and per-user status
- Full audit log with filter by user, subsite, status, and date
- Scheduled background sync via WP-Cron
- Developer action/filter hooks at every sync lifecycle point

## Installation

1. Upload the `multisite-account-sync` folder to `/wp-content/plugins/`
2. **Network Activate** the plugin from Network Admin → Plugins
3. The sync log table is created automatically on all subsites

## File Structure

```
multisite-account-sync/
├── multisite-account-sync.php   # Main bootstrap file
├── uninstall.php                # Clean DB on delete
├── includes/
│   ├── Autoloader.php           # PSR-4 style class loader
│   ├── Plugin.php               # Singleton bootstrap
│   ├── Activator.php            # DB table creation
│   ├── Deactivator.php          # Cron cleanup
│   ├── Sync/
│   │   └── SyncManager.php      # Core sync logic
│   ├── Logs/
│   │   └── LogRepository.php    # DB read/write for audit log
│   └── Admin/
│       └── NetworkPanel.php     # Network admin UI
├── assets/
│   ├── css/
│   └── js/
├── templates/
│   └── admin/
└── languages/
```

## Developer Hooks

```php
// Actions
do_action( 'mcas_before_user_sync', $user_id, $source_blog_id );
do_action( 'mcas_after_user_sync', $user_id, $synced_blogs, $results );
do_action( 'mcas_sync_failed', $user_id, $target_blog_id, $error );

// Filters
apply_filters( 'mcas_sync_user_meta_keys', $meta_keys, $user_id );
apply_filters( 'mcas_blogs_to_sync', $blog_ids, $user_id );
apply_filters( 'mcas_should_sync_user', true, $user_id, $event_type );
```
