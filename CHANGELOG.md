# Changelog

All notable changes to this plugin are documented in this file.
This project adheres to [Semantic Versioning](https://semver.org/).

<!-- new releases inserted below this line -->

## [2.4.14] - 2026-07-20

### Added
- add open-ended widget_config passthrough to profile components

### Fixed
- preserve empty-object shape in requiredResources round-trip
- emit widget_config before plugin-owned keys, not after
- guard widget_config value emit against json_encode() failure
- quote widget_config keys as JSON to prevent stored XSS

### Maintenance
- drop dead duplicate hiddenFields emit


## [2.4.13] - 2026-07-13

### Fixed
- scope role removal to org, handle already-absent roles, log delete errors

### Documentation
- add automated release process to AGENTS.md #norelease
- self-contained release automation reference #norelease
- add release automation reference #norelease


## [2.4.12] - 2026-07-09

### Added
- **ci:** generate CHANGELOG.md entry on release

