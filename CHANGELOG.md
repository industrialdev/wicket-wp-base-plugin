# Changelog

All notable changes to this plugin are documented in this file.
This project adheres to [Semantic Versioning](https://semver.org/).

<!-- new releases inserted below this line -->

## [2.6.15] - 2026-09-03

### Fixed
- **orgss:** guard falsy api client, move helper out of unsorted
- **orgss:** drop deleted organizations from org search results


## [2.6.14] - 2026-09-02

### Fixed
- **groups:** keep API error message a string, errors in data


## [2.6.13] - 2026-09-02

### Performance
- cache touchpoint service ID lookups


## [2.6.12] - 2026-09-01

### Fixed
- json-encode org_id into org-search-select inline script
- default org-search-select org_id for zero-connection people


## [2.6.11] - 2026-08-28

### Fixed
- prevent recursive save on org meta write, fix remaining HPOS read
- let callers opt orders out of auto org assignment


## [2.6.10] - 2026-08-28

### Fixed
- **orgss:** re-sync footer visibility after GF render passes


## [2.6.9] - 2026-08-25

### Fixed
- check for existing MDP bundle membership before creating one


## [2.6.8] - 2026-08-25

### Added
- **woo:** audit-level decision trail for the email blocker

### Fixed
- **woo:** cover generic triggers and tier blocker log levels
- **woo:** scope AutomateWoo marker to the changed transition
- **woo:** carry admin context into async AutomateWoo validation

### Maintenance
- remove tracked .DS_Store files


## [2.6.7] - 2026-08-24

### Added
- register capability key for person_membership is_auto_renew
- **memberships:** accept is_auto_renew on individual membership MDP calls

### Fixed
- type-hint is_autorenew as ?bool on individual membership MDP helpers


## [2.6.6] - 2026-08-24

### Fixed
- resolve the MDP person for order touchpoints instead of trusting user_login
- stop TEC's CSV importer re-importing a batch of rows


## [2.6.5] - 2026-08-21

### Fixed
- **roles:** scope purchase role sync to buyer sessions only
- **roles:** sync WP roles when a membership purchase completes


## [2.6.4] - 2026-08-20

### Fixed
- **orgss:** dedupe hidden field vs GF owner + harden POST echo (WWID-2255)
- **orgss:** drop duplicate hidden input, escape POST echo


## [2.6.3] - 2026-08-20

### Changed
- default event data keys explicitly in the purchase payload builder

### Fixed
- match order attendees by postmeta, not the tickets data API
- build TEC registration touchpoints from attendee posts


## [2.6.2] - 2026-08-20

### Fixed
- keep requiredResources arrays intact in widget profile components

### Documentation
- add PR description template #norelease

### Maintenance
- remove duplicate pull_request_template.md


## [2.6.1] - 2026-08-18

### Added
- **helpers:** add external_id pre-flight lookup for memberships


## [2.6.0] - 2026-08-17

### Added
- **woo:** block emails per order and stop AutomateWoo workflows


## [2.5.7] - 2026-08-13

### Added
- **org-search-select:** auto-hide type field when only one org type


## [2.5.6] - 2026-08-13

### Fixed
- **helpers:** return errors from assign_person_to_org_membership


## [2.5.5] - 2026-08-11

### Added
- **support:** add shared CsvExporter with formula-injection prevention


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

