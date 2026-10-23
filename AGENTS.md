# Repository Guidelines

## Project Structure & Module Organization

This is a WordPress plugin rooted at `wicket.php`. It is the core plugin every other Wicket package depends on.
- `src/`: PSR-4 PHP code under `WicketWP\` (core classes like `Main`, `Assets`, `Rest`, `Blocks`, widget classes in `src/Widgets/`).
- `includes/`: legacy/helpers, integrations, admin settings, and component PHP templates.
- `assets/`: source and built CSS/JS/images/fonts (`assets/css`, `assets/js`, `assets/css/min`, `assets/js/min`).
- `tests/`: Pest/PHPUnit test bootstrap and unit tests (`tests/unit/**/*Test.php`).
- `docs/`: public end-user guides only.

For the package map (stable surfaces, helpers, integrations, settings tabs, MDP sync architecture, touchpoints): `@see atlas://packages/wicket-wp-base-plugin.md`. Follow PHP doc conventions per `@see atlas://conventions/php-doc-comment-style.md`; code doc links (`@see atlas://...`) in source point at the governing Atlas docs.

## Build, Test, and Development Commands

- `composer install`: install PHP dependencies.
- `yarn install` (Node 18.20.7, Yarn 4.7.0 via Volta): install frontend toolchain.
- `npx gulp build`: compile/minify plugin CSS/JS assets.
- `composer lint` / `composer format`: style check / fix (php-cs-fixer).
- `composer test` / `composer test:unit` / `composer test:coverage`: Pest suites and HTML coverage.
- `composer check`: lint + test — run before pushing.
- `composer production`: production install (`--no-dev`, optimized autoloader) before release tags.

## Coding Style & Naming Conventions

- PHP 8.2+, `declare(strict_types=1);`, PSR-12.
- PSR-4 namespaces (`WicketWP\...`); classes in `src/`; classes `PascalCase`, methods `camelCase`, test files end with `Test.php`.
- Favor small methods, early returns, and WordPress-native APIs/hooks.
- Add or update complete PHPDoc blocks on touched functions; inline comments explain why, never what.

## Testing Guidelines

- Frameworks: Pest + PHPUnit + Brain Monkey; unit tests in `tests/unit`.
- Add/update tests for any behavior change, especially widgets, REST handlers, and integration helpers.
- Stack-wide integration tests live in the `qa/` workspace (wicket-warden); read its README and AGENTS.md before adding any.

## Security & WordPress-Specific Requirements

- Sanitize, validate, and escape all input/output (`sanitize_text_field`, `esc_html`, etc.).
- Enforce capability checks and nonces for admin actions and REST endpoints.
- Use `wpdb`/WordPress APIs for data access and preserve backward compatibility.

## Release & Branch Workflow

All work happens on branches. `main` is locked; changes land via peer-reviewed Pull Request. Never commit to `main` directly, and never push or open a PR without explicit human approval. Keep commits focused; PRs should include purpose, risk notes, test evidence, and screenshots for UI changes.

Merging a PR to `main` **auto-releases** via the `wicket-release-bot` GitHub App: version bump, `CHANGELOG.md` update, git tag. Never bump versions or create tags by hand. The bump level comes from a marker in the PR title (squash-merge makes it the commit message): _(none)_ / `#patch` = patch, `#minor`, `#major`, or `#norelease` (no release; use for docs/tooling-only merges). Conventional commit prefixes (`feat:`, `fix:`, `docs:`, ...) drive changelog grouping; a `!` (e.g. `feat!:`) flags a BREAKING change. Full rules: `@see atlas://conventions/release-automation.md`.
