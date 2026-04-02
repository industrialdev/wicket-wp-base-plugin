# Centralized Logging (`WicketWP\Log`)

## Purpose

`WicketWP\Log` is the single logging class for the entire Wicket plugin stack. Every plugin routes its log output through this class so that all Wicket log entries land in consistent, predictable files — no more scattered `error_log()` calls, no WooCommerce logger dependency.

The class is owned by `wicket-wp-base-plugin` and exposed to the rest of the stack via the global `Wicket()` helper.

---

## Log File Location

| WooCommerce active | Directory |
|--------------------|-----------|
| Yes | `wp-content/uploads/wc-logs/` |
| No | `wp-content/uploads/wicket-logs/` |

**Filename pattern:** `wicket-{source}-{Y-m-d}-{hash}.log`

When WooCommerce is present and loaded, Wicket writes into the same `wc-logs/` folder that WooCommerce uses. This means all Wicket log files appear alongside WooCommerce's own logs and are accessible directly from the WordPress admin at **WooCommerce → Status → Logs** — no server access required.

When WooCommerce is not available, logs are written to a dedicated `wicket-logs/` folder inside the uploads directory instead. These files must be accessed directly on the server or via SFTP/SSH.

Each `source` value produces a separate file, so logs from different plugins stay isolated. The directory is created automatically on first use and is protected against direct web access (`.htaccess` + `index.html`).

---

## Log Levels

Five levels are available as class constants on `WicketWP\Log`:

| Constant | Value | Written when |
|----------|-------|--------------|
| `LOG_LEVEL_DEBUG` | `'debug'` | `WP_DEBUG` is `true` |
| `LOG_LEVEL_INFO` | `'info'` | `WP_DEBUG` is `true` |
| `LOG_LEVEL_WARNING` | `'warning'` | `WP_DEBUG` is `true` |
| `LOG_LEVEL_ERROR` | `'error'` | **Always** |
| `LOG_LEVEL_CRITICAL` | `'critical'` | **Always** |

`error` and `critical` are written unconditionally. Everything else is silently dropped when `WP_DEBUG` is falsy, so debug/info/warning calls are safe to leave in production code.

---

## Log Entry Format

Each line written to the log file follows this format:

```
2026-04-02T14:23:01Z [ERROR]: Something went wrong {"source":"wicket-base","order_id":123}
```

Fields:
- Timestamp in ISO 8601 UTC
- Level in uppercase brackets
- The message string
- JSON-encoded context (omitted when context is empty)

---

## How to Use

### The Global Helper

`Wicket()` returns the `WicketWP\Main` singleton. Its `log()` method supports two calling styles:

**Chained (recommended):**
```php
Wicket()->log()->error('Payment failed', ['source' => 'my-plugin', 'order_id' => $order_id]);
Wicket()->log()->warning('Unexpected response', ['source' => 'my-plugin']);
Wicket()->log()->debug('Entering sync routine', ['source' => 'my-plugin']);
```

**Direct:**
```php
Wicket()->log('error', 'Payment failed', ['source' => 'my-plugin', 'order_id' => $order_id]);
```

Both are equivalent. The chained style is preferred because it makes the level explicit at the call site.

### The `source` Context Key

Always pass `source` in the context array. It controls which log file the entry is written to and makes filtering in the WooCommerce log viewer straightforward.

```php
// Good — entry goes to wicket-my-plugin-2026-04-02-{hash}.log
Wicket()->log()->error('Something failed', ['source' => 'wicket-my-plugin']);

// Avoid — falls back to wicket-plugin-{date}-{hash}.log (shared with everything else)
Wicket()->log()->error('Something failed');
```

Use a consistent slug per plugin, e.g. `wicket-finance`, `wicket-guest-payment`.

### Convenience Methods

All five levels are available as direct methods on the `Log` instance:

```php
$log = Wicket()->log();

$log->critical('Fatal condition', ['source' => 'my-plugin']);
$log->error('Recoverable failure', ['source' => 'my-plugin']);
$log->warning('Unexpected but non-fatal', ['source' => 'my-plugin']);
$log->info('User completed checkout', ['source' => 'my-plugin']);
$log->debug('Computed value', ['source' => 'my-plugin', 'value' => $x]);
```

### Direct `log()` Method

When the level is determined at runtime:

```php
$level = $success ? WicketWP\Log::LOG_LEVEL_INFO : WicketWP\Log::LOG_LEVEL_ERROR;
Wicket()->log()->log($level, 'Sync result', ['source' => 'my-plugin', 'success' => $success]);
```

Returns `true` on success, `false` if the directory could not be set up or the file could not be written.

---

## Fatal Error Handler

`WicketWP\Log::registerFatalErrorHandler()` registers a PHP shutdown function that catches fatal errors (`E_ERROR`, `E_PARSE`, `E_CORE_ERROR`, `E_COMPILE_ERROR`, `E_USER_ERROR`) and writes them at `critical` level under the `wicket-fatal-error` source.

The base plugin calls this once in `wicket.php`, before `plugins_loaded`, so it captures errors that occur during WordPress bootstrap:

```php
// wicket.php — runs before plugins_loaded
if (class_exists(WicketWP\Log::class)) {
    WicketWP\Log::registerFatalErrorHandler();
}
```

When WooCommerce is active its own shutdown handler already captures fatals, so `handleFatalError()` bails out early to avoid duplicate entries.

**Do not call `registerFatalErrorHandler()` from your own plugin** — the base plugin handles this for the whole stack.

---

## Adding Logging to a New Plugin

The simplest approach is to call `Wicket()->log()` directly wherever you need it:

```php
// Any class that runs after plugins_loaded
Wicket()->log()->error('Import failed', [
    'source'    => 'wicket-my-plugin',
    'exception' => $e->getMessage(),
]);
```

For a plugin that injects a logger into its service classes, create a thin wrapper class that delegates to `Wicket()->log()`. Look at the existing examples below.

---

## Usage Examples (Outside the Base Plugin)

All examples below assume your code runs after `plugins_loaded`, when `Wicket()` is available.

### Logging inside a WordPress action or filter callback

```php
add_action('wicket_member_created', function (string $person_uuid): void {
    // Happy path
    Wicket()->log()->info('Member created', [
        'source'      => 'wicket-my-plugin',
        'person_uuid' => $person_uuid,
    ]);
});

add_filter('wicket_membership_tier', function ($tier, int $user_id) {
    if (empty($tier)) {
        Wicket()->log()->warning('No membership tier resolved for user', [
            'source'  => 'wicket-my-plugin',
            'user_id' => $user_id,
        ]);
    }
    return $tier;
}, 10, 2);
```

### Wrapping an API call with error logging

```php
function my_plugin_sync_member(string $person_uuid): bool
{
    try {
        $response = wicket_get_person_by_uuid($person_uuid);

        if (empty($response)) {
            Wicket()->log()->warning('Empty MDP response for member sync', [
                'source'      => 'wicket-my-plugin',
                'person_uuid' => $person_uuid,
            ]);
            return false;
        }

        // ... process $response ...

        Wicket()->log()->debug('Member sync complete', [
            'source'      => 'wicket-my-plugin',
            'person_uuid' => $person_uuid,
        ]);

        return true;
    } catch (\Exception $e) {
        Wicket()->log()->error('Member sync threw an exception', [
            'source'      => 'wicket-my-plugin',
            'person_uuid' => $person_uuid,
            'exception'   => $e->getMessage(),
        ]);
        return false;
    }
}
```

### Inside a class — storing the logger in a property

Calling `Wicket()->log()` on every line is fine, but storing the instance avoids the repeated lookup in tight loops or classes with many log calls:

```php
class MyPluginImporter
{
    private \WicketWP\Log $log;

    public function __construct()
    {
        $this->log = Wicket()->log();
    }

    public function run(array $records): void
    {
        foreach ($records as $record) {
            try {
                $this->process($record);
                $this->log->debug('Record imported', [
                    'source' => 'wicket-my-plugin',
                    'id'     => $record['id'],
                ]);
            } catch (\Exception $e) {
                $this->log->error('Record import failed', [
                    'source'    => 'wicket-my-plugin',
                    'id'        => $record['id'],
                    'exception' => $e->getMessage(),
                ]);
            }
        }
    }
}
```

### Creating a wrapper class for constructor injection

When your plugin uses constructor injection, a thin wrapper keeps callsites clean and makes unit testing easier (the wrapper can be swapped for a no-op stub in tests):

```php
namespace MyPlugin\Support;

class Logger
{
    private const SOURCE = 'wicket-my-plugin';

    public function error(string $message, array $context = []): void
    {
        Wicket()->log()->error($message, array_merge(['source' => self::SOURCE], $context));
    }

    public function warning(string $message, array $context = []): void
    {
        Wicket()->log()->warning($message, array_merge(['source' => self::SOURCE], $context));
    }

    public function info(string $message, array $context = []): void
    {
        Wicket()->log()->info($message, array_merge(['source' => self::SOURCE], $context));
    }

    public function debug(string $message, array $context = []): void
    {
        Wicket()->log()->debug($message, array_merge(['source' => self::SOURCE], $context));
    }
}
```

Injecting it:

```php
class MyPluginService
{
    public function __construct(private Logger $logger) {}

    public function do_something(): void
    {
        $this->logger->info('Starting job');
        // ...
    }
}

// Wiring at boot time
$service = new MyPluginService(new \MyPlugin\Support\Logger());
```

### Logging from a theme's `functions.php`

The `Wicket()` helper is available in theme code after `plugins_loaded`. Wrap the call in a guard so the theme degrades gracefully if the base plugin is not active:

```php
// wp-content/themes/my-child-theme/functions.php

add_action('wicket_after_profile_save', function (int $user_id): void {
    if (! function_exists('Wicket')) {
        return;
    }

    Wicket()->log()->info('Profile saved via theme hook', [
        'source'  => 'wicket-my-theme',
        'user_id' => $user_id,
    ]);
});
```

### Conditional debug logging with a plugin-specific constant

Use a constant in `wp-config.php` to enable verbose logging for your plugin only, without turning on `WP_DEBUG` globally:

```php
// wp-config.php
define('MY_PLUGIN_DEBUG', true);
```

```php
// In your plugin code
function my_plugin_log_debug(string $message, array $context = []): void
{
    if (! defined('MY_PLUGIN_DEBUG') || ! MY_PLUGIN_DEBUG) {
        return;
    }
    Wicket()->log()->debug($message, array_merge(['source' => 'wicket-my-plugin'], $context));
}

// Callsite
my_plugin_log_debug('Cache miss', ['key' => $cache_key]);
```

---

## Per-Plugin Wrapper Patterns

### `WicketAcc\Log` (wicket-wp-account-centre)

A backward-compatibility wrapper that keeps the legacy `WACC()->Log()` API working. All methods delegate straight to `Wicket()->log()`. The level constants are re-exported so code that references `WicketAcc\Log::LOG_LEVEL_ERROR` continues to work.

```php
// Internal callsite — still works unchanged
WACC()->Log()->error('Profile update failed', ['source' => 'wicket-acc']);
```

No functional logic lives in this class; it is purely a passthrough.

### `Wicket\Finance\Support\Logger` (wicket-wp-financial-fields)

Injected via constructor into every service class in the financial-fields plugin. Adds a plugin-level debug gate on top of the global `WP_DEBUG` check:

1. `WICKET_FINANCE_DEBUG` constant (defined in `wp-config.php` to force logging on for this plugin only)
2. `wicket/finance/debug_enabled` filter (allows runtime override per request)
3. Falls through to `WP_DEBUG`

`error` and `critical` always bypass this gate.

```php
// Force financial-fields debug logging on without enabling WP_DEBUG globally
define('WICKET_FINANCE_DEBUG', true);
```

```php
// Enable via filter (e.g. for a specific user)
add_filter('wicket/finance/debug_enabled', fn () => current_user_can('manage_options'));
```

### `TraitWicketGuestPaymentLogger` (wicket-wp-guest-checkout)

A PHP trait used by guest payment classes. Its `log()` method signature matches the PSR-3 convention (`message` first, `level` second) and maps PSR-3 levels (`emergency`, `alert`, `notice`) to the base plugin's five levels. The `wicket-guest-payment` source is injected automatically.

```php
// Inside any class that uses the trait
$this->log('Token expired', 'warning', ['order_id' => $order_id]);
```

### `wicket-wp-portus`

Calls `Wicket()->log()` directly at callsites rather than using a wrapper class. Error context uses `wicket-portus` as the source.

---

## Viewing Logs

**When WooCommerce is active:** Go to **WooCommerce → Status → Logs** in the WordPress admin and filter by source name (e.g. `wicket-finance`). Wicket log files appear in the same list as WooCommerce's own logs because they share the `wc-logs/` directory.

**Without WooCommerce:** Log files must be read directly from `wp-content/uploads/wicket-logs/` via the server, SFTP, or SSH. There is no built-in admin UI for this directory.

Files rotate daily. The date in the filename is the calendar date the entry was written (server timezone as configured in `date()`).

---

## Troubleshooting

### Nothing is being written

- Confirm `WP_DEBUG` is `true` in `wp-config.php` for debug/info/warning entries. Error and critical write regardless.
- Check that the `wp-content/uploads/` directory is writable by the web server user.
- Look for `Wicket Log Directory Setup Failed` or `Wicket Log File Write Failed` in the PHP error log — these are written via `error_log()` when the Wicket logger itself cannot write.

### Log file is not appearing under the expected source name

- Verify you are passing `['source' => 'your-source']` in the context array.
- `sanitize_file_name()` is applied to the source value — special characters are stripped. Use lowercase alphanumeric slugs with hyphens only.

### Duplicate fatal error entries

This occurs when `registerFatalErrorHandler()` is called more than once. It should only be called from `wicket.php`. Remove any secondary calls from other plugins.

---

## Source Files

| File | Purpose |
|------|---------|
| `src/Log.php` | Core logging class |
| `src/Main.php` | `log()` accessor method on the singleton |
| `wicket.php` | `Wicket()` global helper; fatal error handler registration |
