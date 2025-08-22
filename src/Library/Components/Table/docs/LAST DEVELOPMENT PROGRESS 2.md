# LAST DEVELOPMENT PROGRESS (Structured) — up to v2.2.1

This document is a structured, chronological log of development from the last noted progress up to the latest fixes and plans. It replaces ad-hoc notes with a clear sequence: What was done, why, results, next steps, and blocking issues.

---

## 0) Status Overview
- Completed (v2.2.1):
  - Sanitization relaxed (commas/slashes preserved), legacy filtering fixed (qualified columns, LIKE/whereIn), SQL query logging
  - Default action behavior restored; helper always receives a valid array
  - Documentation updates: INDEX, ISSUE_ANALYSIS, CHANGELOG
- In Progress:
  - Register other report_* tables into model registry with correct connections/defaults
  - Define initial column-operator presets for key tables
- Next (Phase 3):
  - Operator mapping per column + value adapters (date/numeric ranges)
  - Relationship manifest (config-driven JOINs)
  - Schema discovery layer (cached metadata)
  - Migration guide for incodiy/codiy → canvastack/canvastack

---

## 1) Context Recap (from previous progress)
- Default Action Column behavior restored; helper never receives boolean; defaults ['view','edit','delete'] ensured.
- Ask: Reduce hard-coded elements and move to dynamic, config-driven behavior (model registry, schema probes, etc.).
- Agreement: Gradual refactoring with baseline tests and rollback strategy.

---

## 2) Baseline and Safety Plan
- Backup created for Datatables.php working state.
- Test plan documented (core features, pagination, columns, error isolation, relationships, image detection, formatting).
- Protocol: Test after each phase; rollback on any failures.

---

## 3) Phase 2 Workstream — Bugfix & Hardening (v2.2.1)

### 3.1 Issues observed (GET & POST)
- POST payloads with filters like period_string "25 April, 2023", cor=A, region=CENTRAL SUMATERA, cluster=11 returned empty results.
- GET generally worked, but could still be affected by later sanitization stripping commas.
- Legacy filter application used equality-only where([...]) and unqualified columns.

### 3.2 Root causes
- Sanitization removed commas and slashes, breaking string matches for period-like values and paths.
- Unqualified columns caused ambiguity with joins; equality-only matching limited usability.

### 3.3 Fixes implemented
- Sanitization relaxed in both DataProvider and DataTablesAdapter to allow comma (,) and slash (/).
- Legacy filter application updated in Craft/Datatables.php:
  - Qualify columns with table name
  - Arrays → whereIn
  - Scalars → LIKE "%value%"
  - Enabled SQL query logging for verification

### 3.4 Verification
- POST filters with commas now work when data exists; logs contain "📊 SQL QUERIES WITH FILTERS" showing qualified WHERE clauses.

---

## 4) Configuration Progress
- Model registry entry added for report_data_summary_program_keren_pro_national in config/data-providers.php, with:
  - connection: mysql_mantra_etl
  - primary_key: null
  - default_order: [period_string, desc]
  - searchable/sortable/default columns listed

---

## 5) Documentation Updates
- INDEX.md updated to list v2.2.1 as current.
- ISSUE_ANALYSIS.md: added additional findings and fixes for v2.2.1.
- CHANGELOG.md: new 2.2.1 entry, Unreleased plans expanded.
- Added supporting docs: GET_POST_FILTERING_BUGFIX_ANALYSIS_AND_HARDENING.md, TABLE_FEATURES_MATRIX.md.

---

## 6) Next Development (Phase 3)
- Operator mapping per column (LIKE/= /BETWEEN/IN) + value adapters (date/numeric range parsing).
- Relationship manifest (config-driven JOINs) with aliasing and collision handling.
- Schema discovery layer using cached metadata (column types, indexes, nullability).

---

## 7) Risks, Issues, and Mitigations
- Risk: Enhanced architecture rejects relational filters → Mitigation: Clear fallback to legacy with logs.
- Risk: Broad LIKE semantics in legacy → Mitigation: Operator map per column in Phase 3.
- Risk: Multi-DB/report tables variety → Mitigation: ModelRegistry adaptation and connection retention.

---

## 8) Tracking & Versioning
- Current version: 2.2.1 (Bugfix & Hardening)
- Pending: Migration guide for brand change incodiy/codiy → canvastack/canvastack.

---

## 9) What’s in progress now
- Audit other report_* tables to register in model registry with correct connections and defaults.
- Define initial column operator presets for key tables.

---

## 10) Summary — Default Action Behavior Restored
- Action column always added by default; no conditional skipping.
- Default actions when unspecified: ['view','edit','delete'].
- Helper functions are guaranteed to receive a valid array (never boolean), eliminating foreach errors.
- Expected: no DataTables warnings; action column present; data renders without console errors.

## 11) Hard-coded Elements Audit (Datatables.php)
- Table names: hard-coded model mappings (e.g., users, base_group, base_modules, base_user_group).
- Field names: firstField 'id', default lists ['id'], relationship columns (group_name, group_alias, group_info).
- Table-specific logic: users-specific auto-relationship handling.
- Constants: DEFAULT_ACTIONS, BLACKLISTED_FIELDS, RESERVED_PARAMETERS — acceptable but should move to config.
- Direction: migrate mappings and defaults to configuration/registry; detect schema at runtime; remove table-specific branches.

## 12) Safety-First Refactoring Plan (Agreed)
- Phase 1: Configuration externalization (constants → config). Risk: Low.
- Phase 2: Model mapping dynamics (hard-coded → registry). Risk: Low-Medium.
- Phase 3: Field detection dynamics (schema-driven). Risk: Medium.
- Phase 4: Logic generalization (remove table-specific conditions). Risk: High.
- Guardrails: backup working file; test after each phase; immediate rollback on failure.

## 13) Baseline Test Plan (Must-pass)
- Test 1: Basic rendering for base_module — status 200, data exists, no console errors.
- Test 2: Action column — buttons visible; no "Requested unknown parameter 'action'" warning.
- Test 3: Multi-table coverage — users, base_group, etc., no hard-code dependencies.
- Test 4: Error handling — invalid/empty scenarios produce graceful fallbacks.
- Test 5: Filters — GET/POST parity; recordsFiltered accurate; commas/slashes preserved.
- Test 6: Relationships — foreign key joins function as configured.
- Test 7: Formatting — image fields, conditional labels, dates render correctly.

## 14) Rollback Strategy
- Backup: Datatables_BACKUP_WORKING.php maintained as immediate restore point.
- Procedure: replace Datatables.php with backup; clear caches.
- Version control: commit per phase to enable git revert.

## 15) Decision Log Highlights
- Chosen approach: gradual refactor with baseline verification first.
- Priority: preserve functionality; refactor incrementally with tests.
- Current version: v2.2.1 — filtering stability achieved; logs enabled; docs aligned.

## 16) References
- Issue analysis and root causes: ISSUE_ANALYSIS.md
- Bugfix/hardening details: GET_POST_FILTERING_BUGFIX_ANALYSIS_AND_HARDENING.md
- Feature/API matrix: TABLE_FEATURES_MATRIX.md
- Refactor/hardening plan: REFRACTORING_AND_HARDENING_PLAN.md
- Changelog: CHANGELOG.md