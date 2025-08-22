# Datatables Trait Refactor & Enhancement Roadmap

Version: 1.0  
Status: Approved to start  
Owner: Table System Maintainers

---

## 1) Objectives
- **Modularize Datatables**: Split monolithic logic into cohesive traits with clear responsibilities and contracts.
- **Scalable & Future-ready**: Support dual rendering modes (Blade HTML and React components) via a switchable configuration without breaking existing behavior.
- **Secure-by-default**: Harden inputs/outputs, privilege checks, and query building so that default posture minimizes future hardening needs.
- **Backward compatible**: Maintain legacy behavior and output formats while introducing an enhanced path with feature flags and controlled rollout.

---

## 2) Scope and Non-Goals
- **In-scope**:
  - Trait-based refactor for Datatables orchestration.
  - Enhanced runtime/context management with metrics.
  - Security hardening for filtering, sorting, actions, and responses.
  - Dual render strategy (Blade vs React) with stable, versioned API.
  - Minimal DataProvider enhancements to reduce legacy fallback where safe.
- **Out-of-scope (for now)**:
  - Full redesign of public endpoints or breaking changes to request/response schema.
  - Complex cross-database joins beyond standardized patterns.
  - Frontend React components implementation (this roadmap prepares the backend contracts).

---

## 3) Architecture Overview

### 3.1 Current Architecture (High-level)
- **Legacy Path (Monolithic)**: Datatables.php performs model init, joins, filters, pagination, and output formatting directly.
- **Enhanced Path (Partial)**: Uses `ModelRegistry` (resolution), `DataProvider` (query/data ops), `DataTablesAdapter` (mapping to DataTables format), with fallback to legacy for relational/complex scenarios.

### 3.2 Target Modular Architecture
- **Orchestrator**: `Datatables` becomes a thin coordinator (~800–1200 LOC) that delegates to traits.
- **Traits**:
  - RelationshipHandler, ModelInitializer, FilterHandler, ActionHandler, ColumnHandler, PrivilegeHandler, ResponseHandler, ImageHandler, ImageProcessor.
- **Runtime**: `DatatableRuntime` stores per-table context, caches trait instances, tracks metrics, and validates dependencies.

### 3.3 Dual Rendering Strategy (Blade vs React)
- **Configuration Switch**: `datatables.render_mode` = `blade` | `react`.
- **Blade Mode**: Preserve current behavior (HTML via Blade, server-rendered attributes and actions).
- **React Mode**: Backend returns JSON API with strict, versioned schema. React components consume the API and render on the client.
- **Contract First**: API is the contract; rendering layer becomes replaceable.

```php
// config/datatable.php (example)
return [
    'use_traits' => true,               // Feature flag for trait orchestration
    'render_mode' => 'blade',           // 'blade' | 'react'
    'security' => [
        'enforce_strict_filters' => true,
        'max_page_size' => 200,
        'allowed_operators' => ['=', '!=', '>', '>=', '<', '<=', 'LIKE', 'IN', 'BETWEEN'],
    ],
    'relationships' => [
        'enabled' => true,
        'safe_join_patterns' => ['fk_standard'], // define safe join templates
    ],
    'api' => [
        'version' => 'v1',
        'etag' => true,
        'cache_ttl' => 30, // seconds
    ],
];
```

---

## 4) API Contract for React Consumption (v1)
- **Endpoint pattern**: `POST /api/v1/datatables/{table}/query`
- **Request** (subset aligned with DataTables + secure extensions):
```json
{
  "draw": 3,
  "start": 0,
  "length": 25,
  "order": [{"column": "created_at", "dir": "desc"}],
  "columns": [
    {"data": "id", "searchable": true, "orderable": true},
    {"data": "name", "searchable": true, "orderable": true}
  ],
  "filters": {
    "name": {"op": "LIKE", "value": "%john%"},
    "created_at": {"op": "BETWEEN", "value": ["2024-01-01", "2024-12-31"]}
  },
  "context": {"tenant": "A"}
}
```
- **Response**:
```json
{
  "draw": 3,
  "recordsTotal": 1234,
  "recordsFiltered": 120,
  "data": [
    {"id": 10, "name": "John", "created_at": "2024-05-01T12:00:00Z"}
  ],
  "meta": {
    "columns": [
      {"name": "id", "type": "int", "visible": true},
      {"name": "name", "type": "string", "visible": true}
    ],
    "actions": [
      {"key": "edit", "label": "Edit", "method": "GET", "url": "/users/10/edit"}
    ]
  }
}
```
- **Guarantees**:
  - Strict allowlist for columns/operators.
  - Stable schema within `v1`.
  - ETag/conditional GET optional for caching.

---

## 5) Feature Flags & Backward Compatibility
- **Flags**:
  - `use_traits`: enable/disable trait-driven orchestration.
  - `render_mode`: `blade` or `react`.
  - `relationships.enabled`: toggle relational support in enhanced path.
  - `security.enforce_strict_filters`: enforce allowlist validation.
- **Fallbacks**:
  - If a trait or enhanced path raises an error or encounters unsupported patterns, orchestrator automatically falls back to legacy logic to preserve output.
- **Deprecation Policy**:
  - Announce legacy-only code paths as deprecated after parity is achieved; retain for two minor releases before removal.

---

## 6) Security Hardening (Secure-by-Default)
- **Input validation**:
  - Allowlist columns and operators. Reject unknown operators, nested fields, raw SQL snippets.
  - Normalize and validate pagination (length caps) and sorting fields.
- **Query safety**:
  - Prepared statements/parameter binding exclusively.
  - Minimal raw SQL; if needed, parse and restrict via `extractTableNameFromSQL` and safe templates.
- **Privilege enforcement**:
  - Centralize checks in `PrivilegeHandler`; all actions filtered by role/permission matrix.
- **Output sanitization**:
  - Escape HTML in Blade; sanitize strings and enforce types for JSON.
- **Auditability**:
  - Optional lightweight logging hooks for privileged actions and security denials.
- **Abuse protection**:
  - Rate limits (middleware), per-request cost cap (e.g., max join depth, max page size), timeouts.

---

## 7) Trait Responsibilities & Contracts

### 7.1 ModelInitializer
- **Responsibilities**: Instantiate models/builders from table name, raw SQL, or query builders; integrate `ModelRegistry` and connection management.
- **Inputs**: table name | raw SQL | builder; runtime context.
- **Outputs**: normalized data source (Eloquent/Builder) + metadata (table, connection).
- **Failure modes**: unknown table/model, unsafe SQL → return error to orchestrator for fallback.

### 7.2 FilterHandler
- **Responsibilities**: Validate and apply filters; consolidate multi-filter inputs; support operators (`LIKE`, `BETWEEN`, `IN`).
- **Security**: allowlist operators/columns; reject ambiguous fields; date-range validation.
- **Outputs**: sanitized filter set and applied conditions.

### 7.3 PrivilegeHandler
- **Responsibilities**: Role/permission validation and action filtering.
- **Outputs**: pruned actions and visibility map per row/table.

### 7.4 RelationshipHandler
- **Responsibilities**: Setup joins/relations (User-Group patterns + generic FK joins); validate relationship integrity.
- **Outputs**: adjusted builder with joins; mapping for related columns.

### 7.5 ActionHandler
- **Responsibilities**: Build action buttons/configs; conditional display; route mapping.

### 7.6 ColumnHandler
- **Responsibilities**: Ordering, raw columns, visibility, type detection, dynamic column generation.

### 7.7 ResponseHandler
- **Responsibilities**: JSON formatting, error handling, data transformation, response caching hooks.

### 7.8 ImageHandler / 7.9 ImageProcessor
- **Responsibilities**: Detect image fields, validate extensions/paths, generate HTML/props; support responsive and placeholders.

---

## 8) DatatableRuntime Enhancements
- **Capabilities**:
  - Cache trait instances per table.
  - Store trait contexts (filters, relations, columns) per request/session.
  - Collect metrics (time per trait, cache hits/misses).
  - Validate trait dependency order.
- **Sketch**:
```php
class DatatableRuntime {
    private static array $traitInstances = [];
    private static array $traitContext = [];
    private static array $traitMetrics = [];

    public static function loadTraitInstance(string $table, string $class): object { /* ... */ }
    public static function setTraitContext(string $table, string $trait, array $ctx): void { /* ... */ }
    public static function getTraitContext(string $table, string $trait): ?array { /* ... */ }
    public static function startTimer(string $table, string $trait): void { /* ... */ }
    public static function endTimer(string $table, string $trait): void { /* ... */ }
    public static function metrics(string $table): array { /* ... */ }
}
```

---

## 9) DataProvider Enhancements (Minimal & Safe)
- **Relational filters (basic)**: Support a safe subset (standard FK join templates), reject complex expressions.
- **Consistency**: Single path for pagination/sorting; central sanitization.
- **Caching**: Optional in-memory per-request caches for schema metadata and prepared joins.

---

## 10) Migration Plan & Milestones

### Phase 1 (Week 1–2) — Core & Security
1. DatatableRuntime enhancements (context/cache/metrics).  
2. ModelInitializer extraction + `ModelRegistry` integration.  
3. FilterHandler extraction + strict validation (LIKE/BETWEEN/IN).  
4. PrivilegeHandler extraction + role matrix hooks.  
5. Orchestrator wiring behind `use_traits` flag; output parity tests.

### Phase 2 (Week 3–4) — Relations, Actions, Columns
6. RelationshipHandler (User-Group + generic FK joins; validation).  
7. ActionHandler (dynamic, conditional, privilege-aware).  
8. ColumnHandler (ordering/raw/visibility; type detection).  
9. Reduce legacy fallback for common relational scenarios.

### Phase 3 (Week 5) — Responses, Images, Polish
10. ResponseHandler (formatting, error handling, caching hooks).  
11. ImageHandler + ImageProcessor (detect/validate/render).  
12. Performance tuning via runtime metrics; lazy trait loading.

---

## 11) Testing Strategy
- **Unit tests**: Each trait’s pure logic (filters, privilege checks, column ordering).
- **Integration tests**: Full Datatables flow (legacy vs enhanced) with parity for JSON.
- **Contract tests**: API schema for `v1` (request/response validation with JSON Schema).
- **Golden tests**: Snapshot outputs for critical tables/views.
- **Security tests**: Injection attempts, privilege bypass, operator abuse, pagination abuse.
- **Performance tests**: Benchmarks before/after; thresholds defined per table size.

---

## 12) Performance & Scalability Guidelines
- **Lazy loading**: Instantiate traits on first use per request.
- **Pagination**: Enforce caps; prefer indexed sorts; leverage covering indexes.
- **Query plans**: Avoid N+1; use standardized joins; keep select lists minimal.
- **Caching**: Schema metadata, prepared joins, ETag for API responses.
- **Concurrency**: Stateless APIs; avoid session locks on data queries; scoped caches.

---

## 13) Delivery Checklist (Per Phase)
- Traits implemented with docblocks and method summaries.
- Runtime metrics show no major regressions.
- All tests (unit, integration, contract, golden) pass.
- Feature flags default to safe values in production.
- Documentation updated (this file + trait READMEs as needed).

---

## 14) Risks & Mitigations
- **Regression risk**: Golden tests + automatic fallback to legacy on unsupported patterns.
- **Performance risk**: Measure with runtime metrics; optimize hot paths; cap page sizes.
- **Complexity risk**: Strict trait boundaries; clear contracts and inputs/outputs.
- **Security risk**: Allowlist-first design; central privilege checks; reject unsafe inputs early.

---

## 15) Backward Compatibility & Deprecation
- Maintain legacy path until enhanced achieves parity + 2 minor releases.
- Provide toggles and clear migration notes per release.
- Avoid breaking public request/response shapes inside `v1`.

---

## 16) Appendix

### 16.1 Mapping (source → trait)
- Lines 749–888 → ModelInitializer (createFrom..., extractTableNameFromSQL, tryCreateSpecificModel)
- Lines 857–884, 877–880 → RelationshipHandler (user/group joins, relationship columns)
- Lines 1427–1550 → FilterHandler (apply/process/consolidate/isValid)
- Lines 1134, 1276–1319 → ActionHandler + PrivilegeHandler (filter by privileges, route mapping)
- Lines 1658–1700 → ColumnHandler (ordering/raw columns)
- Lines 1770, 1787, 2350–2426 → ImageHandler (detect/process)
- Lines 2435–2519 → ImageProcessor (HTML generation/validation)

### 16.2 Example Orchestrator Trait Use
```php
class Datatables {
    use RelationshipHandler;
    use ModelInitializer;
    use FilterHandler;
    use ActionHandler;
    use ColumnHandler;
    use PrivilegeHandler;
    use ResponseHandler;
    use ImageHandler;
    use ImageProcessor;

    // Core orchestration only; feature flags and fallback to legacy retained.
}
```

### 16.3 React Integration Notes
- Keep API stable and documented; React consumes the same endpoints.
- Provide a thin data-access layer in React that mirrors server filters/operators.
- Consider SSR or hydration later; initial target is CSR with ETag caching.

---

## Definition of Done (Overall)
- Datatables orchestrator reduced significantly with traits in place.
- Dual render switch functional; Blade parity preserved; React-ready API stable.
- Security posture is default-strong; inputs validated; outputs sanitized; privileges centralized.
- Performance at least on par; runtime metrics available; no major regressions.