# Changelog

All notable changes to this plugin are documented in this file.
This project adheres to [Semantic Versioning](https://semver.org/).

<!-- new releases inserted below this line -->

## [2.5.4] - 2026-08-11

### Fixed
- **helpers:** log failures in wicket_update_membership_external_id


## [2.5.3] - 2026-08-06

### Added
- **orgss:** wire up auto_advance to advance GF page after org select


## [2.5.2] - 2026-08-05

### Fixed
- **banner:** show CTA callout without a link row


## [2.5.1] - 2026-08-04

### Fixed
- configurable card-featured image ratio, natural blurred-fill frame, Members Only tag seat


## [2.5.0] - 2026-08-04

### Added
- TEC touchpoints for admin and CSV attendee adds, removals and registration answers #minor


## [2.4.23] - 2026-08-04

### Fixed
- TEC touchpoint duplicate writes, venue-less events and null person_id


## [2.4.22] - 2026-07-30

### Fixed
- **service-identities:** send attributes as object not array


## [2.4.21] - 2026-07-30

### Added
- **helpers:** add slug-based service resolver
- **helpers:** generic service-identity CRUD + person wrappers


## [2.4.20] - 2026-07-30

### Other
- Clarifying what the demo link should be
- Update CONTRIBUTING.md
- Added Alex's suggestion to PR template
- Creating a pull request template and contributing guidelines for the project.


## [2.4.19] - 2026-07-27

### Added
- **helpers:** add service identity mint helper for MDP sequential generation

### Fixed
- **helpers:** address review on service identity helpers
- **helpers:** key active-memberships memoization by UUID


## [2.4.18] - 2026-07-27

### Fixed
- reset sidebar contextual nav body content per accordion item


## [2.4.17] - 2026-07-24

### Other
- Honor email_type and phone_type extras in wicket_create_or_get_person


## [2.4.16] - 2026-07-21

### Added
- **modal:** add dual-mode modal component, trigger, and get_modal_pair helper


## [2.4.15] - 2026-07-21

### Added
- **helpers:** defensive logs on org assign failure for QA observability
- **helpers:** add wicket_supports capability registry and overflow flag on org assign
- **memberships:** add copy_previous_assignments param to org assignment


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

