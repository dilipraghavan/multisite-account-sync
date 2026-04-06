# Multisite Central Account Sync

A WordPress multisite plugin that automatically synchronizes user accounts, roles, and capabilities across all network subsites. This project serves as a portfolio piece to demonstrate proficiency in enterprise WordPress architecture, multisite network management, and scalable plugin development.

---

## Features

- **Automatic User Sync:** User profiles, meta, roles, and capabilities propagate to all subsites automatically on profile update, role change, or new registration — no manual intervention required.

- **Network Admin Panel:** A dedicated Network Admin page showing sync status across the network, per-site user counts, and aggregate success/failure statistics.

- **Manual Sync Controls:** Trigger a full network sweep or sync a specific user on demand directly from the admin UI.

- **Full Audit Log:** Every sync event is recorded with user, source site, target site, action type, status, and timestamp. Filterable and paginated using `WP_List_Table`.

- **WP-Cron Scheduler:** Background sync job runs every 6 hours automatically, with last run summary and next run time displayed in the admin panel.

- **Developer Hooks:** Action and filter hooks at every sync lifecycle point — before sync, after sync, on failure, filterable meta keys, filterable target blogs.

- **Clean Architecture:** Service class structure with single-responsibility components. `SyncManager`, `UserReplicator`, `LogRepository`, `HookManager`, and `Scheduler` each own exactly one concern.

---

## Installation

### Prerequisites

- WordPress 6.0 or higher
- PHP 8.0 or higher
- WordPress Multisite enabled

### Setup

**Option A — Download the release zip (recommended):**

1. Download the latest `multisite-account-sync.zip` from [Releases](https://github.com/dilipraghavan/multisite-account-sync/releases)
2. Go to **Network Admin → Plugins → Add New → Upload Plugin**
3. Upload the zip and click **Network Activate**

**Option B — Clone the repository:**

1. Clone into your WordPress plugins directory:
```bash
git clone https://github.com/dilipraghavan/multisite-account-sync.git
```

2. Go to **Network Admin → Plugins** and click **Network Activate**

### After Activation

- The plugin creates `wp_mcas_sync_log` on every subsite automatically
- Confirm the cron job is scheduled:
```bash
wp cron event list | grep mcas
```

You should see `mcas_scheduled_sync` listed with a 6-hour recurrence.

---

## Usage

### Automatic Sync

Once network activated, syncing is fully automatic. The following WordPress events trigger a sync:

- User profile updated (`profile_update`)
- User role changed (`set_user_role`)
- New user registered (`user_register`)
- User deleted (`delete_user` / `wpmu_delete_user`)

### Manual Sync

1. Go to **Network Admin → Account Sync**
2. Click **Sync All Users** to sweep the entire network
3. Or select a specific user from the dropdown and click **Sync User**

### Viewing the Audit Log

1. Go to **Network Admin → Account Sync → Sync Log**
2. Filter by status, action type, or target site
3. Sort by any column
4. Use bulk actions to delete old entries

### Scheduled Sync

The background cron job runs every 6 hours and records results in the network options. The next run time and last run summary are displayed in the Account Sync admin panel.

---

## Developer Hooks

```php
// Actions
do_action( 'mcas_before_user_sync', $user_id, $source_blog_id, $action );
do_action( 'mcas_after_user_sync', $user_id, $synced_blogs, $results );
do_action( 'mcas_sync_failed', $user_id, $target_blog_id, $error_message );

// Filters
apply_filters( 'mcas_sync_user_meta_keys', $meta_keys, $user_id );
apply_filters( 'mcas_blogs_to_sync', $blog_ids, $user_id, $source_blog_id );
apply_filters( 'mcas_should_sync_user', true, $user_id, $action );
```

**Example — restrict sync to specific subsites:**

```php
add_filter( 'mcas_blogs_to_sync', function( $blogs, $user_id, $source ) {
    // Only sync to blogs 2 and 3.
    return array_intersect( $blogs, [ 2, 3 ] );
}, 10, 3 );
```

**Example — add custom meta keys to sync:**

```php
add_filter( 'mcas_sync_user_meta_keys', function( $keys, $user_id ) {
    $keys[] = 'billing_address';
    $keys[] = 'company_name';
    return $keys;
}, 10, 2 );
```

---


## Tech Stack

- **Platform:** WordPress Multisite 6.0+
- **Language:** PHP 8.0+
- **Database:** MySQL via `$wpdb` with `dbDelta()` for table management
- **Architecture:** Service class pattern with DAL separation
- **Admin UI:** Native WordPress `WP_List_Table`, `wp_nonce_field`, `wp_safe_redirect`
- **Scheduling:** WP-Cron with custom intervals
- **Multisite API:** `switch_to_blog()`, `get_sites()`, `is_user_member_of_blog()`, `add_user_to_blog()`

---

## Contributing

If you find a bug or want to suggest an improvement:

1. Fork the repository
2. Create a new branch for your fix or feature
3. Commit your changes with a clear and concise message
4. Push your branch to your forked repository
5. Submit a pull request

---

## License

This project is licensed under the MIT License. See the [LICENSE](https://github.com/dilipraghavan/multisite-account-sync/blob/main/LICENSE) file for details.
