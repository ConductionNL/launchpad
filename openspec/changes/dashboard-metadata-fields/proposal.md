# Dashboard Metadata Fields

## Why

Today MyDash dashboards have no way for administrators to attach custom, queryable metadata to every dashboard. Users often need to tag dashboards with attributes like "department", "project stage", "audience segment", or custom organizational dimensions so that dashboards can be filtered, grouped, or faceted in search and discovery. Without this capability, administrators must either mandate a naming convention (fragile and unscalable) or build ad-hoc workarounds. This change introduces a global metadata field registry that administrators can configure once, with per-dashboard field values that end users populate and that the system can query for filtering.

## What Changes

- Add a new `oc_mydash_meta_fields` table to persist global field definitions: each field has a machine-name `field_key` (unique, lowercase, alphanumeric with underscores), a display `label`, a `type` (text, number, date, select, multi-select, boolean), optional `options` for select types, a `required` flag, sort order, and timestamps.
- Add a new `oc_mydash_meta_values` table to persist field values per dashboard: each row links a dashboard UUID to a field ID and stores the value (type-encoded: JSON for arrays, ISO-8601 for dates, decimal string for numbers, "0"/"1" for booleans).
- Add admin CRUD endpoints for field definitions: `GET|POST /api/admin/metadata-fields`, `GET|PUT|DELETE /api/admin/metadata-fields/{id}` — admin-only via `IGroupManager::isAdmin`.
- Add value read/write endpoints per dashboard: `GET /api/dashboards/{uuid}/metadata` (returns a flat key-value object); `PUT /api/dashboards/{uuid}/metadata` (upserts every key in the request body, ignoring omitted keys).
- Add type validation at write time: reject non-numeric input for number fields, non-existent options for select fields, null/empty for required fields (HTTP 400).
- Forbid renaming field `field_key` (unique stable identifier — admins must create a new field and migrate manually). Allow edits to `label`, `sortOrder`, `required`, and `options`.
- Add cascading delete for field definitions with explicit `?cascade=true` confirmation.
- Add `GET /api/dashboards?metadata.<key>=<value>` filtering with support for exact match (text/select/boolean) and range queries (number/date via `min` / `max` suffix).
- Handle stale field references gracefully: if a value row references a deleted field, hide it from read responses and surface orphans in admin tooling without crashing.

## Capabilities

### New Capabilities

- `dashboard-metadata-fields` — a new, self-contained capability for defining, populating, querying, and validating custom metadata on dashboards.

### Modified Capabilities

(none — this is a new, standalone capability with no changes to existing capabilities)

## Impact

**Affected code:**

- `lib/Db/MetadataField.php` (new entity) — field definition model with id, fieldKey, label, type, options, required, sortOrder, createdAt, updatedAt
- `lib/Db/MetadataFieldMapper.php` (new mapper) — CRUD for field definitions; find by key; find all; ensure key uniqueness
- `lib/Db/MetadataValue.php` (new entity) — value record model with id, dashboardUuid, fieldId, value
- `lib/Db/MetadataValueMapper.php` (new mapper) — CRUD for values; find by dashboard; upsert; delete by field with cascade
- `lib/Service/MetadataService.php` (new service) — coordinate field + value operations; validate field type matching; handle orphan detection; route filtering queries
- `lib/Service/MetadataValidationService.php` (new service) — per-type value validation
- `lib/Exception/InvalidMetadataFieldException.php` (new exception) — HTTP 400 validation errors
- `lib/Exception/MetadataFieldHasValuesException.php` (new exception) — HTTP 409 for delete with values
- `lib/Controller/MetadataAdminController.php` (new controller) — admin field definition endpoints
- `lib/Controller/DashboardMetadataController.php` (new controller) — per-dashboard metadata read/write
- `appinfo/routes.php` — register 7 new routes (admin field CRUD + dashboard metadata read/write + list filtering)
- `lib/Migration/Version001016Date20260502130000.php` (new migration) — create `oc_mydash_meta_fields` and `oc_mydash_meta_values` tables with proper indexes
- `src/stores/dashboard.js` (modified) — track metadata per dashboard; expose getter for field definitions
- `src/services/api.js` (modified) — add 7 client wrappers for metadata endpoints
- `src/components/Dashboard/DashboardMetadataPanel.vue` (new component) — per-dashboard form to populate metadata values
- `l10n/{en,nl}.{json,js}` — 8 new keys covering the panel UI surface

**Affected APIs:**

- 5 new admin routes: 2 for field listing/creation, 3 for field detail/edit/delete
- 2 new dashboard routes: 1 for metadata read, 1 for metadata write
- Dashboard list/get routes support `metadata.*` filter syntax

**Dependencies:**

- `OCP\IGroupManager` — already injected elsewhere, used to check admin status
- No new composer or npm dependencies (all validation is built-in)

**Migration:**

- Zero-impact: new tables only, no existing data affected
- The migration creates two new tables with proper composite indexes for fast lookups by dashboard and field
