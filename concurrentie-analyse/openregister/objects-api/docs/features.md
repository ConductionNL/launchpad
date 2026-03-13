# Objects API — Complete Feature List

## Objecttypes API Features

1. **JSON-schema validation** — Only valid JSON-schemas allowed in objecttypes
2. **Versioning** — Objecttypes are versioned; new versions for evolving definitions
3. **Admin interface** — Create and inspect objecttypes via UI
4. **Draft/Published workflow** — Versions start as draft (editable), then published (immutable)
5. **Metadata** — Rich metadata: name, description, classification, contact info, source, update frequency
6. **Data classification** — open, intern, confidential, strictly_confidential
7. **Labels** — Key-value pairs for tagging objecttypes

## Objects API Features

1. **Objecttype validation** — Data validated against JSON-schema on create/update
2. **Formal and material history** — Two-axis history tracking (StUF standard)
   - Material history: startAt/endAt (real-world dates)
   - Formal history: registrationAt (administrative dates)
3. **Geographic search** — GeoJSON Point, Polygon, LineString, MultiPoint, etc.
4. **Arbitrary attribute filtering** — Filter on any JSON data attribute
5. **Authorizations** — Per-objecttype read/write access via API tokens
6. **Field-based authorization** — Restrict access to specific fields (read-only mode)
7. **Superuser tokens** — Full access tokens for dev/test
8. **Notifications** — Integration with Notificaties API (create/update/delete events)
9. **Notification auto-retry** — Binary exponential backoff for failed notifications
10. **Correction records** — Records cannot be changed; corrections create new records linking to the corrected one
11. **Object history** — Full record history per object
12. **GeoJSON support** — Full GeoJSON geometry types (EPSG:4326)
13. **Pagination** — Default 100, max 500 per page
14. **Field selection** — `fields` query parameter for sparse responses
15. **Ordering** — Sort by any field including nested data attributes
16. **Data text search** — `data_icontains` for searching across all string values
17. **Date-based queries** — `date` and `registrationDate` query parameters
18. **PATCH with merge** — Partial update merges recursively with existing data
19. **Admin object management** — Create/update objects via admin UI
20. **Admin search** — Search objects by UUID or key__operator__value patterns
21. **OpenID Connect SSO** — OIDC for admin interface login
22. **OpenTelemetry** — Full OTel metrics, logging, tracing support
23. **Elastic APM** — Application performance monitoring
24. **Celery task queue** — Async notification delivery
25. **CORS support** — Configurable cross-origin settings
26. **CSP headers** — Content Security Policy configuration
27. **Connection pooling** — Experimental PostgreSQL connection pooling
28. **CSV export** — Data dump to SQL or CSV
29. **2FA** — Two-factor authentication (can be disabled)
30. **Sentry integration** — Error monitoring
31. **Cloud Events** — Experimental zaak-gekoppeld/zaak-ontkoppeld events
32. **Open Archiefbeheer** — Support for destruction workflows via references field
33. **Zaak references** — Objects can reference multiple zaken
