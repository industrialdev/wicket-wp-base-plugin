---
title: "Release Automation (Auto Version Bump & Tag)"
audience: [developer]
source_files: [".github/workflows/release.yml", ".ci/version-bump.php", ".ci/changelog.php"]
---

# Release Automation

Every merge into `main` bumps the plugin version and pushes a matching git tag automatically. The bump runs *after* merge, computed serially from `main`, so a version can never collide with one chosen inside a parallel PR. No one needs push access to `main`, and releases are attributed to a GitHub App, not a person.

---

## What happens on merge

1. A PR merges into `main` (push event).
2. [.github/workflows/release.yml](../../.github/workflows/release.yml) mints a GitHub App token, checks out `main`.
3. It reads the merge commit message for a bump marker and runs [.ci/version-bump.php](../../.ci/version-bump.php).
4. It runs [.ci/changelog.php](../../.ci/changelog.php) to prepend a `CHANGELOG.md` section for the new version.
5. It commits `chore(release): x.y.z` (version files + changelog), tags that commit `x.y.z`, and pushes both to `main`.

The workflow ignores its own `chore(release):` commits, so it does not loop.

## Choosing the bump level

Default is a **patch** bump (`2.4.10` → `2.4.11`). Override by putting a marker anywhere in the merge commit message. With GitHub's default squash-merge, the PR title becomes that message, so the marker can go in the PR title.

| Marker | Result | Example |
|---|---|---|
| _(none)_ | patch | `2.4.10` → `2.4.11` |
| `#minor` | minor | `2.4.10` → `2.5.0` |
| `#major` | major | `2.4.10` → `3.0.0` |
| `#norelease` | no bump, no tag | (skipped) |

## What gets bumped

The version is written to two files, kept in sync:

- `composer.json` `version` field (the source of truth the script reads).
- The main plugin file header `Version:` (auto-detected: the root `*.php` whose header contains `Plugin Name:`).

`readme.txt` `Stable tag` is intentionally not touched (these plugins are not WP.org-hosted; that field is unused here).

## Changelog

`CHANGELOG.md` (repo root, [Keep a Changelog](https://keepachangelog.com) style, newest on top) is updated automatically in the same release commit. [.ci/changelog.php](../../.ci/changelog.php) reads the commits merged since the last tag (`git log <last-tag>..HEAD --no-merges`) and prepends one section for the new version.

Every commit type is included and grouped by its conventional prefix. The only commits skipped are the bot's own `chore(release):` commits (they are the changelog commits themselves). Commits with no conventional prefix are listed verbatim under **Other**.

| Prefix | Section | | Prefix | Section |
|---|---|---|---|---|
| `feat` | Added | | `test` | Tests |
| `fix` | Fixed | | `build` | Build |
| `perf` | Performance | | `ci` | CI |
| `refactor` | Changed | | `chore`, `style` | Maintenance |
| `docs` | Documentation | | _(none / unknown)_ | Other |

A `!` in the prefix (e.g. `feat!:`) prefixes the line with **BREAKING**.

New sections are inserted below the `<!-- new releases inserted below this line -->` marker, so the file header stays fixed. Cleaner commit subjects (or squash-merge, so one PR is one line) produce a cleaner changelog.

Regenerate or back-fill a range manually with an explicit range argument:

```bash
php .ci/changelog.php 2.4.11 2.4.8..2.4.11
```

## Local use

The same script backs a Composer command for manual bumps:

```bash
composer version-bump patch     # 2.4.10 -> 2.4.11
composer version-bump minor     # 2.4.10 -> 2.5.0
composer version-bump major     # 2.4.10 -> 3.0.0
composer version-bump 2.4.11    # set an explicit version
composer version-bump           # prompt interactively
```

It only edits files; it does not commit or tag.

---

## One-time setup

The workflow authenticates as a GitHub App so releases are decoupled from any individual account and do not expire like a personal token.

### 1. Create the App (org level)

Org → Settings → Developer settings → GitHub Apps → New GitHub App.

- **Repository permissions → Contents: Read and write** (the only permission needed).
- Uncheck **Webhook → Active**.
- Create, then **Generate a private key** (downloads a `.pem`). Note the **App ID**.

### 2. Install the App

Install it on the `industrialdev` org and grant it access to the plugin repositories.

### 3. Store the secrets

Set these as **organization** secrets (Org → Settings → Secrets and variables → Actions), scoped to the plugin repos, so all plugins share one App:

| Secret | Value |
|---|---|
| `RELEASE_APP_ID` | the App's numeric App ID |
| `RELEASE_APP_PRIVATE_KEY` | the full contents of the downloaded `.pem` |

### 4. Allow the App to bypass branch protection

`Contents: write` is not enough on a protected branch. On the `main` branch protection rule (or ruleset), add the App to the **bypass list** ("Allow specified actors to bypass required pull requests"). Without this, the workflow's push to `main` is rejected.

---

## Troubleshooting

| Symptom | Cause | Fix |
|---|---|---|
| Push rejected: `protected branch` | App not on the bypass list | Add the App to the branch-protection / ruleset bypass list |
| `Bad credentials` / token step fails | Wrong `RELEASE_APP_ID` or malformed `RELEASE_APP_PRIVATE_KEY` | Re-copy the whole `.pem`, including header/footer lines |
| Workflow runs but does nothing | `#norelease` in the message, or it was the workflow's own `chore(release):` commit | Expected; check the run log's "Resolved bump level" line |
| `No version field found in composer.json` | Plugin's `composer.json` has no `version` key | Add a `version` field matching the current header |
| Wrong file bumped / "No version string found" | Main file not auto-detected (no `Plugin Name:` header in a root `*.php`) | Ensure the main plugin file lives in the repo root with a standard header |
