# Spec: Product Instance Management

## Feature Summary

Management of individual product instances -- specific government services delivered to citizens or businesses. Each product is linked to a product type and contains owner information, status lifecycle, custom data, and references to external cases/documents/tasks.

## Capabilities

### Product CRUD
- Create/read/update/delete product instances
- Each product linked to exactly one ProductType
- Product name, publication status (gepubliceerd)
- Price and payment frequency (eenmalig / maandelijks / jaarlijks)

### Status Lifecycle
- Seven predefined statuses: initieel, in_aanvraag, gereed, actief, ingetrokken, geweigerd, verlopen
- Status constrained by ProductType's `toegestane_statussen` list
- Automatic status transitions:
  - `start_datum` reached -> status set to `actief` (if allowed)
  - `eind_datum` reached -> status set to `verlopen` (if allowed)
- Date-driven automation handled by Celery periodic tasks

### Owner Identification
- Multiple owners per product (Eigenaar entity)
- Identification via BSN (citizen service number), KvK number, vestigingsnummer, or klantnummer
- Since v1.6.0: eigenaar URN field for standardized references
- Filtering on any owner identifier

### Flexible Data Fields
- `dataobject` (JSON) -- arbitrary product-specific data
- `verbruiksobject` (JSON) -- consumption/usage data
- Both validated against JSON Schemas defined on the ProductType
- Both searchable via `dataobject_attr` and `verbruiksobject_attr` query parameters with operators

### External References
- `aanvraag_zaak` (URN/URL) -- the originating case from which the product was created
- Documents (1:N) -- references to EnkelvoudigInformatieObject in Documenten API
- Zaken (1:N) -- references to Zaak in Zaken API (since v1.2.0)
- Taken (1:N) -- references to tasks in external APIs (since v1.2.0)
- All URN/URL with automatic mapping

### Authorization (since v1.5.0)
- Class-level: user needs `producten.add_product`, `producten.change_product`, `producten.delete_product`
- Object-level: user needs read or read-write permission on the specific ProductType
- Superusers bypass object-level checks

### Notifications
- CRUD events published on `producten` channel via VNG Notificaties API
- Message includes producttype.uuid, uniforme_product_naam, and code

### Filtering
- By product type (uuid, code, naam, theme, location, organisation)
- By owner (BSN, KvK, vestigingsnummer, klantnummer)
- By status, dates (start, end, creation, update), price
- By referenced documents, cases, tasks (URL and URN)
- By JSON field values (dataobject_attr, verbruiksobject_attr)

## OpenRegister Equivalent

OpenRegister can model product instances as objects of a `Product` schema with:
- ProductType relation to a product type object
- Owner as embedded object or related `Eigenaar` schema
- Status as enum property
- dataobject/verbruiksobject as nested JSON properties
- External references as URL/string properties

**OpenRegister advantages:**
- Schema extensibility -- add new product fields without code changes
- Audit trail on every product change
- Soft deletes and version history
- Full-text search across all product data
- Faceted search on any field
- AI/semantic search on product content
- Webhooks for real-time event notification
- Multi-tenancy for separating product data by organization

**Open Product advantages:**
- Built-in status lifecycle with automatic date-driven transitions
- Native owner identification with BSN/KvK validation
- Per-producttype object-level authorization
- VNG Notificaties API integration
- JSON field filtering with rich operators (gt, lt, icontains, in)
- Dedicated Celery tasks for status automation
