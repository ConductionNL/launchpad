# Design — Dashboard Metadata Fields

## Context

This capability is a MyDash-native invention — not present in the source app. Admins define typed metadata fields; users and widgets store values against them. The spec (`dashboard-metadata-fields`) pins the two-table model, CRUD endpoints, type enum, and filter-by-metadata query contract.

The primary driver is dashboard filtering and organization: administrators need a way to classify dashboards (e.g. by department, project stage, audience segment) so that dashboards can be discovered, filtered, and faceted in search. Without this, users must rely on naming conventions or ad-hoc tagging schemes.

## Goals / Non-Goals

**Goals:**

- Document the type enum and value encoding per type
- Specify orphan-value handling (field deleted while values exist)
- Clarify cascade-on-field-delete safety mechanism
- Define filter-by-metadata query approach
- Specify validation failure response shape
- Ensure metadata read/write respects dashboard ownership and group permissions

**Non-Goals:**

- Widget-side consumption of metadata (each widget spec owns that)
- Admin UI layout decisions (frontend concern)
- Per-user metadata field visibility rules (not in spec)
- Bulk metadata update operations (single dashboard at a time)

## Decisions

### D1: Type enum

**Decision**: Six types — `text`, `number`, `date`, `select`, `multi-select`, `boolean`. Stored as VARCHAR(20).

**Alternatives considered:**

- `url`/`email` as first-class types — deferred; handle as text+regex validation for now
- `richtext` — rejected; XSS surface not justified at MVP

**Rationale**: Covers all dashboard-filtering and widget-configuration use cases from requirements triage. Type validation is per-field and enforced at write time.

### D2: Value encoding strategy

**Decision**: TEXT column in `oc_mydash_meta_values.value`. Plain string for scalar types; JSON array string for `multi-select`. `boolean` stored as `"1"`/`"0"`. `number` stored as decimal string (e.g. "42.5"). `date` stored as ISO-8601 string (e.g. "2026-05-21").

**Alternatives considered:**

- Typed columns per type — rejected; painful migrations when adding types
- JSON column for all — rejected; MariaDB JSONB support inconsistent across supported NC versions

**Rationale**: TEXT is universally supported across SQLite, MySQL, and PostgreSQL; `MetadataService` and `MetadataValidationService` parse/validate on read/write.

### D3: Validation failure response

**Decision**: Type-mismatched value write returns HTTP 400 `{"error":"<message>","field":"<label>"}`.

**Rationale**: REQ-MDFL-006 specifies validation at write time with user-facing error messages. Server-side error prefixes are not translated to keep the API contract stable for SDK consumers.

### D4: Orphan-value tolerance

**Decision**: Deleting a field does NOT auto-delete its values. `MetadataService::getMetadataForDashboard` hides orphaned values via bulk `findByIds` lookup on field definitions — rows whose field_id has no matching definition are silently skipped and logged at warning level. Non-cascade deletes are rejected with HTTP 409 if values exist.

**Alternatives considered:**

- Hard cascade on every delete — rejected; silent irreversible data loss
- Soft-delete field — adds complexity without MVP benefit

**Rationale**: Preserving orphan rows is safer; explicit `?cascade=true` provides governance for intentional cleanup.

### D5: Cascade-on-field-delete safety gate

**Decision**: `DELETE /api/admin/metadata-fields/{id}` without `?cascade=true` returns HTTP 409 if values exist. `?cascade=true` deletes values and the field definition transactionally.

**Alternatives considered:**

- Always cascade — rejected; matches the philosophy in dashboard-tree and other delete flows for consistency
- Soft-delete field — deferred; not required for MVP

**Rationale**: Explicit opt-in for destructive operations. Two-step pattern used consistently across MyDash delete flows to prevent accidental data loss.

### D6: Filter-by-metadata query approach

**Decision**: Dashboard list endpoint accepts `?metadata.<fieldKey>=<value>` params for exact match (text/select/boolean) and `?metadata.<fieldKey>.min=<value>&metadata.<fieldKey>.max=<value>` for range queries (number/date). Translated to `INNER JOIN oc_mydash_meta_values` with indexed WHERE clauses per param. Unknown filter keys are silently dropped.

**Alternatives considered:**

- Full-text search across value column — rejected; too broad, breaks type semantics
- POST body filter — rejected; GET semantics for list queries, bookmarkable URLs
- Array containment for multi-select — deferred; today matches exact JSON string representation

**Rationale**: SQL join approach is simple, indexed on `(dashboard_uuid, field_id)`, and keeps query logic server-side rather than loading all metadata to filter in PHP. Unknown keys drop silently so stale URLs don't empty the list when a field is deleted.

### D7: Field-key uniqueness scope

**Decision**: Field keys are globally unique (admin-defined, reused across all dashboards).

**Alternatives considered:**

- Per-dashboard scope — rejected; widget configs reference fields by key (e.g. `meta.status`), so global uniqueness makes configs portable
- Per-group scope — too complex; global is simpler and fields are admin-governed

**Rationale**: Global uniqueness simplifies widget configuration and filtering; fields are admin-controlled, not per-dashboard inventions.

### D8: Permission model for metadata read/write

**Decision**: Metadata read/write is scoped to dashboard ownership (for personal dashboards) or group access (for group-shared dashboards). Same permission model as dashboard GET/PUT.

**Rationale**: Metadata is part of the dashboard; users should not be able to read or modify metadata of dashboards they cannot already access.

### D9: Required field handling

**Decision**: When `required=1` on a field definition, any dashboard MUST have a value (not null, not empty string). Attempting to set a required field to null/empty returns HTTP 400 with the field's label in the error message.

**Rationale**: Ensures data integrity for critical fields. Optional fields allow deletion via explicit null value.

## Risks / Trade-offs

- **Multi-select filter** — `?metadata.tags=news` matches exact JSON string, not containment; document this; array containment is a follow-up
- **Filter fan-out** — each `?metadata.*` param adds a JOIN; implementation caps at 5 simultaneous filters to avoid runaway queries
- **Orphaned values** — If a field is deleted without cascade, orphaned value rows remain in the database. Admin interface can surface these for cleanup.

## Open follow-ups

- Add `contains` operator for multi-select filter (JSON_CONTAINS or PHP post-filter)
- Bulk metadata operations (batch update across multiple dashboards)
- Admin dashboard to list orphaned metadata values and clean them up
- Metadata audit trail (track who changed what and when)
