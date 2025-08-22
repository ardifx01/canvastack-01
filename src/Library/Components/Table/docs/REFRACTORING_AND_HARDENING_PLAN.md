# Refactoring & Hardening Plan (Phase 2 → Phase 4)

This is the authoritative roadmap for the Table component evolution.

## Phase 2 (current)
- Consolidate model registry entries in config/data-providers.php (incl. report_* tables).
- Stabilize Enhanced Architecture for non-relational filters; orchestrate fallback to legacy for relational filters.
- Harden sanitization and reserved parameter filtering; keep business-safe punctuation.
- Documentation: GET/POST bugfix, features matrix, changelog.

## Phase 3
- Schema-aware discovery layer with cached metadata: column types, indexes, nullability.
- Column operator map per field (LIKE, =, BETWEEN, IN) + value adapters (date parsing, numeric ranges).
- Relationship manifest: config-driven joins with aliasing and collision-safe selection.
- Unified query builder that composes filters, joins, sorting using the manifest.

## Phase 4
- Remove remaining table-specific conditionals from runtime; rely entirely on registry + manifests.
- Transformation pipelines: pre-query, post-query, pre-render hooks with tests.
- Replace hard-coded constants by configuration; configuration validation rules.
- Full test coverage: unit + integration + end-to-end fixtures for large data.

## Security Hardening (continuous)
- Strict reserved-parameter exclusion; CSRF handling; token rejection in filters.
- SQL keyword scrubbing; allow list of punctuation (',','/','-','.', '@', '+', '_').
- XSS-safe renderers and formatter escaping; HTML whitelist for action buttons.
- Rate-limiting and pagination bounds enforcement on heavy endpoints.

## Observability
- Structured logging: filter extraction, exclusions, applied queries, counts.
- Optional slow-query logging threshold and diagnostics.
- Debug toggles in config to reduce log verbosity in production.

## Brand Migration (planning)
- Namespace and package rename: incodiy/codiy → canvastack/canvastack.
- Provide a migration guide (composer.json replace, namespaces, providers, assets tags).
- Dual-namespace shim during transition window.