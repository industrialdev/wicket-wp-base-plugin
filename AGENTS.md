# Repository Guidelines

## Project Structure & Module Organization
This is a WordPress plugin rooted at `wicket.php`.
- `src/`: PSR-4 PHP code under `WicketWP\\` (core classes like `Main`, `Assets`, `Rest`, `Blocks`, widget classes in `src/Widgets/`).
- `includes/`: legacy/helpers, integrations, admin settings, and component PHP templates.
- `assets/`: source and built CSS/JS/images/fonts (`assets/css`, `assets/js`, `assets/css/min`, `assets/js/min`).
- `tests/`: Pest/PHPUnit test bootstrap and unit tests (`tests/unit/**/*Test.php`).
- `docs/`: feature docs (for example WooCommerce email blocker notes).

## Build, Test, and Development Commands
- `composer install`: install PHP dependencies.
- `yarn install` (Node 18.20.7, Yarn 4.7.0 via Volta): install frontend toolchain.
- `npx gulp build`: compile/minify plugin CSS/JS assets.
- `composer lint`: style check (`php-cs-fixer --dry-run --diff`).
- `composer format` or `composer cs:fix`: apply formatting.
- `composer test`: run full Pest suite.
- `composer test:unit`: run unit tests only.
- `composer test:coverage`: generate HTML coverage in `coverage/`.
- `composer production`: production install (`--no-dev`, optimized autoloader) before release tags.

## Coding Style & Naming Conventions
- PHP 8.2+, `declare(strict_types=1);`, PSR-12.
- Use PSR-4 namespaces (`WicketWP\\...`) and keep classes in `src/`.
- Naming: classes `PascalCase`, methods `camelCase`, test files end with `Test.php`.
- Favor small methods, early returns, and WordPress-native APIs/hooks.

## Testing Guidelines
- Frameworks: Pest + PHPUnit + Brain Monkey.
- Unit tests live in `tests/unit`; browser suite is configured as `tests/Browser` when present.
- Add/update tests for any behavior change, especially widgets, REST handlers, and integration helpers.
- Run `composer check` before pushing (lint + test).

## Commit & Pull Request Guidelines
Git history favors short, imperative, scope-specific messages (for example `fixes improper org uuid`, `docs: email blocker`).
- Keep commits focused; avoid mixed refactor/feature changes.
- PRs should include: purpose, risk notes, test evidence (`composer check` output), and screenshots for UI/admin changes.
- Link related issue/ticket and call out breaking or release-impacting changes.

## Security & WordPress-Specific Requirements
- Sanitize, validate, and escape all input/output (`sanitize_text_field`, `esc_html`, etc.).
- Enforce capability checks and nonces for admin actions and REST endpoints.
- Use `wpdb`/WordPress APIs for data access and preserve backward compatibility.
