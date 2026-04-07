---
title: "Centralized Logging"
audience: [developer, agent]
php_class: WicketWP\Log
source_files: ["src/Log.php", "src/Main.php", "wicket.php"]
---

# Centralized Logging (`WicketWP\Log`)

**Developer reference** — single logging class for the entire Wicket plugin stack.

The class is owned by `wicket-wp-base-plugin` and exposed via the global `Wicket()` helper.

## Log File Location

| WooCommerce active | Directory |
|--------------------|-----------|
| Yes | `wp-content/uploads/wc-logs/` |
| No | `wp-content/uploads/wicket-logs/` |

**Filename pattern:** `wicket-{source}-{Y-m-d}-{hash}.log`

When WooCommerce is active, logs appear at **WooCommerce → Status → Logs**. Without WooCommerce, access files directly on the server.

## Log Levels

| Constant | Value | Written when |
|----------|-------|--------------|
| `LOG_LEVEL_DEBUG` | `'debug'` | `WP_DEBUG` is `true` |
| `LOG_LEVEL_INFO` | `'info'` | `WP_DEBUG` is `true` |
| `LOG_LEVEL_WARNING` | `'warning'` | `WP_DEBUG` is `true` |
| `LOG_LEVEL_ERROR` | `'error'` | **Always** |
| `LOG_LEVEL_CRITICAL` | `'critical'` | **Always** |

## Log Entry Format

```
2026-04-02T14:23:01Z [ERROR]: Something went wrong {"source":"wicket-base","order_id":123}
```

Fields: timestamp (ISO 8601 UTC), level (uppercase), message, JSON context.

## Usage

**Chained (preferred):**
```php
Wicket()->log()->error('Payment failed', ['source' => 'wicket-my-plugin', 'order_id' => $order_id]);
Wicket()->log()->warning('Unexpected response', ['source' => 'wicket-my-plugin']);
Wicket()->log()->debug('Entering sync routine', ['source' => 'wicket-my-plugin']);
```

**Direct:**
```php
Wicket()->log('error', 'Payment failed', ['source' => 'my-plugin']);
```

**Always pass `source`** in the context array — it controls the log filename and enables filtering in the WooCommerce log viewer. Use a consistent hyphenated slug per plugin (e.g. `wicket-finance`, `wicket-guest-payment`).

**Convenience methods:**
```php
$log = Wicket()->log();
$log->critical('Fatal condition', ['source' => 'my-plugin']);
$log->error('Recoverable failure', ['source' => 'my-plugin']);
$log->warning('Unexpected but non-fatal', ['source' => 'my-plugin']);
$log->info('User completed checkout', ['source' => 'my-plugin']);
$log->debug('Computed value', ['source' => 'my-plugin', 'value' => $x]);
```

**Dynamic level:**
```php
$level = $success ? WicketWP\Log::LOG_LEVEL_INFO : WicketWP\Log::LOG_LEVEL_ERROR;
Wicket()->log()->log($level, 'Sync result', ['source' => 'my-plugin']);
```

## Per-Plugin Wrapper Pattern

A thin wrapper keeps callsites clean and enables testing with a no-op stub:

```php
namespace MyPlugin\Support;

class Logger
{
    private const SOURCE = 'wicket-my-plugin';

    public function error(string $message, array $context = []): void
    {
        Wicket()->log()->error($message, array_merge(['source' => self::SOURCE], $context));
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

## Existing Stack Wrappers

| Plugin | Wrapper | Notes |
|--------|---------|-------|
| `wicket-wp-account-centre` | `WicketAcc\Log` | Backward-compat passthrough to `Wicket()->log()` |
| `wicket-wp-financial-fields` | `Wicket\Finance\Support\Logger` | Adds `WICKET_FINANCE_DEBUG` constant gate + `wicket/finance/debug_enabled` filter |
| `wicket-wp-guest-checkout` | `TraitWicketGuestPaymentLogger` | PSR-3 signature mapping, auto-injects `wicket-guest-payment` source |
| `wicket-wp-portus` | — | Calls `Wicket()->log()` directly |

## Fatal Error Handler

`WicketWP\Log::registerFatalErrorHandler()` is called once in `wicket.php` (before `plugins_loaded`). It catches fatal PHP errors and writes them at `critical` level under `wicket-fatal-error`.

**Do not call this from other plugins** — the base plugin handles it for the whole stack.

```php
// wicket.php
if (class_exists(WicketWP\Log::class)) {
    WicketWP\Log::registerFatalErrorHandler();
}
```

## Viewing Logs

- **With WooCommerce:** **WooCommerce → Status → Logs** — filter by source name
- **Without WooCommerce:** read directly from `wp-content/uploads/wicket-logs/`

Files rotate daily. Date in filename is the calendar date the entry was written.

## Troubleshooting

**Nothing being written:**
- `WP_DEBUG` must be `true` for debug/info/warning entries. `error` and `critical` always write.
- `wp-content/uploads/` must be writable by the web server user.

**Log file not appearing under expected source name:**
- Pass `['source' => 'your-source']` in the context array.
- `sanitize_file_name()` is applied — use lowercase alphanumeric with hyphens only.

**Duplicate fatal error entries:**
- `registerFatalErrorHandler()` was called more than once. It belongs only in `wicket.php`.

## Source Files

| File | Purpose |
|------|---------|
| `src/Log.php` | Core logging class |
| `src/Main.php` | `log()` accessor on the singleton |
| `wicket.php` | `Wicket()` global helper; fatal error handler registration |
