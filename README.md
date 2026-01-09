# Wicket WordPress Base Plugin

Core foundation plugin for Wicket's WordPress ecosystem. Provides shared functionality, widgets, blocks, and API integration for other Wicket plugins.

## Installation

This plugin is not available in the WordPress.org plugin repository. It is distributed to Wicket clients for implementation by a developer who will add the plugin according to the project code process.

## Requirements

- **WordPress**: 6.0+
- **PHP**: 8.2+
- **Composer**: For dependency management

## Development

### Setup

```bash
# Install dependencies
composer install
```

### ⚠️ IMPORTANT: Before Tagging a New Version

**Always run `composer production` before tagging a new version.** This command:
- Removes development dependencies
- Optimizes autoloader for production
- Generates a clean build without dev packages

```bash
composer production
```

Without this step, the plugin will include unnecessary dev dependencies in the release.

### Running Tests

The plugin uses **PEST** and **PHPUnit** for testing.

```bash
# Run all tests
composer test

# Run unit tests only
composer test:unit

# Run tests with coverage report
composer test:coverage

# Run browser tests
composer test:browser

# Run specific test file
./vendor/bin/pest tests/unit/MainTest.php
```

### Writing New Tests

1. **Create test file** in `tests/unit/` with pattern `*Test.php`
2. **Extend AbstractTestCase** for WordPress function mocking

```php
<?php

declare(strict_types=1);

namespace WicketWP\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use WicketWP\Main;

#[CoversClass(Main::class)]
class MyNewTest extends AbstractTestCase
{
    private Main $main;

    protected function setUp(): void
    {
        parent::setUp();
        $this->main = Main::get_instance();
    }

    public function test_something(): void
    {
        $this->assertTrue(true);
    }
}
```

3. **Use Brain Monkey** to mock WordPress functions:

```php
\Brain\Monkey\Functions\stubs([
    'get_option' => 'value',
    'add_action' => null,
    'add_filter' => null,
]);
```

4. **Run tests** - PHPUnit auto-discovers test files matching `*Test.php`

### Test Structure

```
tests/
├── bootstrap.php              # PHPUnit bootstrap with WordPress mocks
└── unit/
    ├── AbstractTestCase.php   # Base test class with Brain Monkey setup
    ├── MainTest.php           # Main class tests
    ├── ConstantsTest.php      # Plugin constants tests
    └── Widgets/
        ├── CreateAccountWidgetTest.php
        ├── UpdatePasswordWidgetTest.php
        └── ManagePreferencesWidgetTest.php
```

### Code Style

```bash
# Check code style
composer lint

# Fix code style automatically
composer format
```

### Building Assets

```bash
# Build CSS/JS assets
npm run build
```

### Available Composer Scripts

```bash
composer production       # Build for production (remove dev deps, optimize autoload)
composer test            # Run all tests
composer test:unit       # Run unit tests only
composer test:coverage   # Run tests with HTML coverage report
composer test:browser    # Run browser tests
composer lint            # Check code style
composer format          # Fix code style
composer check           # Run lint + test
composer version-bump    # Bump plugin version
```

## Style Notes

There is a placeholder `theme.json` file in the root of the plugin folder that is only there to provide an easy 'default styles' reference to Tailwind (WordPress should ignore it just fine), should we need to use fallback styles on a site that isn't running a Wicket theme. There is a similar fallback enqueue for Alpine in that scenario as well.

## SSO Tip

When running the wp-cassify plugin for SSO, you can bypass the SSO login if needed using this URL:

```
https://localhost/wp/wp-login.php?wp_cassify_bypass=bypass
```

Rarely do we recommend logging into a Wicket-powered site directly without going through SSO, but there might be cases when that is needed, such as to rescue a site or reconfigure SSO/Wicket plugin settings locally after bringing down a production DB.

## Pantheon Gotcha

You will need to (when using wp-cassify) delete the mu-plugin `wp-native-php-sessions.php` as well as the folder `wp-native-php-sessions`. This causes errors when trying to login.

## Architecture

### Main Components

- **Main** (`src/Main.php`) - Core singleton, component initialization
- **Assets** (`src/Assets.php`) - Frontend/admin asset management
- **Rest** (`src/Rest.php`) - REST API endpoints
- **Blocks** (`src/Blocks.php`) - Gutenberg blocks registration
- **Widgets** (`src/Widgets/`) - WordPress widgets (CreateAccount, UpdatePassword, ManagePreferences)
- **Includes** (`src/Includes.php`) - Include file management

### Widgets

- **CreateAccount** - User registration widget
- **UpdatePassword** - Password change widget
- **ManagePreferences** - User preferences widget

## Hooks & Filters

### Actions

```php
// Fired during plugin initialization
do_action('wicket_init');
```

### Filters

```php
// Modify Wicket API base URL
apply_filters('wicket_api_url', $url);
```

## Support

- **Issues**: [GitHub Issues](https://github.com/industrialdev/wicket-wp-base-plugin/issues)
- **Documentation**: [Wicket Developer Docs](https://wicket.io/docs)

## License

GPL v2 or later

## Credits

Developed by [Wicket](https://wicket.io)
