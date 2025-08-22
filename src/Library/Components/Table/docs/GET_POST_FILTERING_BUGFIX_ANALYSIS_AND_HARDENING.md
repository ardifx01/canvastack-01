# Phase 2 — GET/POST Filtering Bugfix, Hardening, and Refactor Plan

Date: 2025-08-20
Status: Completed (bugfix), In-Progress (Phase 2 refactor/hardening)
Target: Table component server-side processing (Enhanced Architecture + Legacy Fallback)

---

## 1) Executive Summary

- Issue: POST-based filtering returned empty/zero results and sometimes GET/POST behaved inconsistently.
- Root causes:
  - Over-aggressive sanitization removed commas in values like "25 April, 2023" causing mismatches.
  - Legacy filter path applied `where($processedFilters)` without qualifying columns or handling arrays → ambiguous columns and equality-only filtering.
  - Enhanced Architecture rejects relational filters (needs JOIN) by design and requires controlled fallback.
- Fixes implemented:
  - Sanitization relaxed to allow comma and slash in both Enhanced and Adapter layers.
  - Legacy path now qualifies columns with the table name, uses LIKE for strings, and whereIn for arrays.
  - Diagnostics/logging improved for applied filters and generated SQL.
- Next steps (Phase 2):
  - Complete configuration-driven model registry for report tables.
  - Standardize fallback rules Enhanced → Legacy for relational filters.
  - Harden security and input validation.

---

## 2) Scope of changes

Files updated:
- Providers/DataProvider.php
  - sanitizeString(): allow comma (,) and slash (/)
- Adapters/DataTablesAdapter.php
  - sanitizeString(): allow comma (,) and slash (/)
- Craft/Datatables.php
  - applyFilters(): qualify columns and support LIKE/whereIn; log executed queries

All changes are backward compatible and covered by extra runtime logs for verification.

---

## 3) Detailed analysis: GET vs POST behavior

### 3.1 GET method
- Symptoms historically: Worked more reliably because values like `period_string=25 April, 2023` arrived intact; but when sanitization removed commas later in the pipeline, it could still 0-match on some datasets.
- With the relaxed sanitization, GET flows keep commas and now match string values correctly.

### 3.2 POST method
- Symptoms: Frontend confirmed POST interception and submission with valid payload (incl. difta, csrf, and filters) but server responded with `{recordsTotal:0, recordsFiltered:0, data:[]}`.
- Root causes:
  - String sanitization stripped commas from `period_string`, breaking exact/LIKE matches.
  - Legacy path used `$model->where($processedFilters)` (equality only, no table qualifier) which fails in JOIN contexts or partial string matches.
- Fix behavior:
  - `sanitizeString()` now keeps commas and slashes; dates like `25 April, 2023` are preserved.
  - Legacy path now iterates processed filters and:
    - Qualifies as `{$table}.{$column}` unless already qualified.
    - Applies `whereIn` for arrays, and `LIKE %value%` for single values.

### 3.3 Enhanced Architecture vs relational filters
- Enhanced Phase 2 is intentionally strict: when detecting relational filters (e.g. `group_name`, `group_info`, etc.), it raises an exception prompting fallback to the Legacy path.
- Current behavior is correct-by-design; the legacy path is responsible for JOIN-based filtering.

---

## 4) Fixes: code notes

### 4.1 Sanitization updates
- Providers/DataProvider.php → sanitizeString():
  - Old allowed chars: letters, numbers, space, `-`, `.`, `@`, `+`, `_`.
  - New allowed chars: adds comma (`,`) and slash (`/`).

- Adapters/DataTablesAdapter.php → sanitizeString(): same allowance for consistency.

### 4.2 Legacy filter application (Craft/Datatables.php)
- Before: single `$model->where($processedFilters)` → equality only; ambiguous; no `whereIn`.
- After: per-filter application with qualification and operators:
  - Arrays → `whereIn`
  - Scalars → `LIKE %value%`
  - Always qualify to `{$table}.{$column}`
  - Query logging enabled to aid verification in `storage/logs/laravel.log`.

---

## 5) Verification guide

1) Submit POST request with payload including:
   - difta[name]=report_data_summary_program_keren_pro_national
   - period_string=25 April, 2023
   - cor=A
   - region=CENTRAL SUMATERA
   - cluster=11
2) Expected:
   - Non-zero `recordsFiltered` if data exists for that period/region/cor/cluster.
   - laravel.log contains `📊 SQL QUERIES WITH FILTERS` showing:
     - `period_string LIKE "%25 April, 2023%"`
     - `cor LIKE "%A%"`
     - `region LIKE "%CENTRAL SUMATERA%"`
     - `cluster LIKE "%11%"` or `whereIn` if multiple.

If results are 0 with existing data, capture the logged SQL section for inspection.

---

## 6) Hardening plan (Phase 2)

- Input validation and normalization
  - Keep strict exclusion of control params (draw, columns, order, start, length, search, csrf, token, etc.).
  - Preserve benign punctuation required for business data (`,`, `/`).
  - Continue flattening and deduping for nested arrays (safe `whereIn`).

- Fallback orchestration
  - Enhanced rejects explicit relational filters → clean error → Datatables main flow falls back to legacy with JOIN support.
  - Maintain clear log messages for fallback reasons.

- Table/model registry
  - Continue consolidating report table configs in `config/data-providers.php`.
  - Use `ModelRegistry` to adapt similar report-table families (same connection, model class; dynamic `setTable`).

- Security controls
  - Keep SQL keyword scrubbing in sanitizeString.
  - Ensure CSRF token pass-through (never treated as filter).
  - Add rate-limited paths for heavy endpoints.

- Observability
  - Maintain structured logs for: extracted filters, excluded params, applied SQLs, counts (total/filtered).

---

## 7) Refactor roadmap (incremental)

- Phase 2 (Current)
  - Externalize more hard-coded pieces into configuration (completed for `report_data_summary_program_keren_pro_national`).
  - Stabilize Enhanced-Adapter-Provider flow for non-relational filters.
  - Strengthen legacy fallback for relational cases.

- Phase 3
  - Schema-based field discovery with cached metadata for large schemas.
  - Unified operator mapping (equality, LIKE, range, date ops) via configuration per-column.
  - Relationship manifest for JOIN assembly (config-driven).

- Phase 4
  - Generalize and remove all table-specific logic/conditionals in runtime path.
  - Introduce transformation pipelines (pre-query, post-query, pre-render) with test coverage.

---

## 8) Known limitations & next actions

- Enhanced Architecture intentionally doesn’t auto-JOIN (requires explicit config or fallback).
- Some legacy functions use broad LIKE matching for convenience; allow opt-in exact match or operator control per column.
- Provide thorough examples for multi-DB connections and temp tables.

Immediate next:
- Add column-operator configuration support to legacy applyFilters (e.g., exact for numeric, LIKE for text).
- Document relational filter keys per table for predictable behavior.

---

## 9) Appendix: Feature list for $this->table()

High-level capabilities (see TABLE_FEATURES_MATRIX.md for the exhaustive list and code examples):
- Method control: GET/POST with secure mode.
- Filtering: text, select, multiselect, daterange, numberrange, boolean; custom filters; server-side.
- Sorting: default and request-driven; primary key fallback; configurable model defaults.
- Pagination: start/length; chunking; lazy loading.
- Relations: JOIN-based via config; model-provided relationships; fallback strategies.
- Formatting: date, currency, conditional labels, images; custom renderers and transforms.
- Actions: CRUD and custom buttons, privilege-aware, removable buttons.
- Export: CSV/PDF/Excel/Print; customization options per format.
- UI: responsive, header manipulation, column visibility, grouping/merging headers.
- Charts: basic integration and data pipelines.
- Performance: caching, indexing guidance, query logging, state persistence.
- Security: CSRF, reserved params exclusion, SQL keyword scrubbing, XSS handling in formatters.