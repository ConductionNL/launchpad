# Cross-Reference Matrix: 28 Tender Specs vs. 408 Competitor Specs

Generated: 2026-03-15

This document maps each of our 28 tender-derived specifications to overlapping competitor specs from the competitive analysis corpus (408 specs across 20 competitors in 3 categories: procest, pipelinq, openregister).

---

## 1. rbac-zaaktype (OpenRegister)
**Tender demand:** 86%
**Competitors with this capability:**

| Competitor | Their spec | Approach | What we can learn |
|------------|-----------|----------|-------------------|
| ArkCase | `access-control` | Dual-tier model: row-level `AcmParticipant` entries per object + functional access via LDAP-mapped application roles. Access filters baked into Solr queries. | Participant-type enum (assignee, owning group, follower, approver, reader) is richer than simple CRUD groups. Consider adding role-type semantics beyond group membership. |
| CaseFabric | `case-team-authorization` | Case-team-based authorization: users are added to a case team with a specific role. CMMN-level authorization tied to case plan items. | Case-team model (authorization scoped to individual cases, not globally) is valuable for multi-tenant zaaktype setups. |
| CaseFabric | `multitenancy-authorization` | Multi-tenant isolation with per-tenant role assignments. Authorization checked at both API gateway and engine level. | Tenant-scoped RBAC is essential if we serve multiple gemeenten from one instance. |
| Dimpact ZAC | `access-control-policies` | Policy-based access control with zaaktype-level group restrictions. Groups are assigned per zaaktype in admin configuration (`zaakafhandelparameters`). | Very close to our design. Their `zaakafhandelparameters` per zaaktype is exactly the "authorization block per schema" pattern we propose. Validate their UX for group assignment. |
| Flowable | `identity-management` | Spring Security integration with LDAP/OIDC. Role-to-privilege mapping at process definition level. Tenant-aware identity. | Flowable separates identity management from authorization policy -- cleaner architecture. Consider decoupling our identity source from the authorization engine. |
| Valtimo | `authorization-pbac` | Policy-Based Access Control (PBAC) with JsonSchema-based policies evaluated at runtime. Policies can reference zaaktype, role, and field values. | Most sophisticated approach. JsonSchema policies are flexible but complex. Their approach may be over-engineered for our needs, but the concept of policy-as-data (not code) is worth adopting. |
| OpenZaak | `autorisaties-model` | Token-scoped authorizations: each API consumer gets a token with explicit permissions per zaaktype (CRUD + confidentiality level). Checked via `Autorisatie` objects linked to `Applicatie`. | Standard ZGW authorization model. We must be compatible with this for ZGW interoperability but should layer richer RBAC on top. |
| xxllnc Zaken | `authentication` | OIDC-based with role extraction from JWT claims. Roles mapped to permissions in the zaaksysteem. | Standard OIDC approach. Nothing unique, but confirms OIDC+role-claims is the expected baseline. |
| Baserow | `permissions-rbac` | Three-tier: workspace roles (Admin/Member), API token permissions (per-table CRUD), Enterprise RBAC with custom roles and field-level permissions. | API token with per-table CRUD matrix is a clean model for service-to-service auth. Relevant for our schema-level RBAC on API consumers. |
| Directus | `access-control` + `row-level-security` | Role-based policies with granular CRUD permissions per collection. Row-level security via custom filter rules per role. Supports field-level read/write permissions. | Directus combines collection-level RBAC with row-level filters -- exactly our two-layer model (rbac-zaaktype + rbac-scopes). Their admin UI for policy configuration is a good reference. |
| NocoBase | `access-control` | Plugin-based RBAC with configurable permissions per collection and action. Supports custom roles with fine-grained action permissions. | Plugin architecture means permissions are extensible. Consider making our RBAC hookable for app-specific extensions. |
| Strapi | `access-control` | Role-based permissions per content-type and action. Separate "public" and "authenticated" permission sets. Admin and API roles are separate systems. | Separation of admin roles vs API roles is a pattern we should adopt -- Nextcloud admin vs API consumer permissions. |
| Objects API | `authorization` + `field-level-authorization` | Token-based authorization per objecttype with CRUD permissions. Field-level authorization restricts which fields a token can read/write. | Close to our rbac-scopes. Their field-level auth is per-token, not per-group -- different model but same goal. |

**Key takeaway:** All competitors implement zaaktype-level RBAC. The pattern of "group/role -> zaaktype -> CRUD permissions" is universal. Our design is well-aligned. Adopt: policy-as-data pattern (Valtimo), case-team scoping (CaseFabric), participant-type semantics (ArkCase). Avoid: over-engineering with JsonSchema policy evaluation at runtime -- start simple with group-CRUD matrices.

---

## 2. archivering-vernietiging (OpenRegister)
**Tender demand:** 73%
**Competitors with this capability:**

| Competitor | Their spec | Approach | What we can learn |
|------------|-----------|----------|-------------------|
| OpenZaak | `archivering` | Full Archiefwet-compliant lifecycle: archiefnominatie, archiefactiedatum, archiefstatus. Resultaattypen define retention per zaaktype. Status transitions enforce archiving rules. | The reference implementation for Dutch archiving. We must match their data model (archiefnominatie enum: `blijvend_bewaren`, `vernietigen`; archiefstatus: `nog_te_archiveren`, `gearchiveerd`, `overgedragen`). |
| xxllnc Zaken | `archiving` | Automated destruction with configurable retention per zaaktype. Background jobs handle batch destruction. Audit trail of all destruction actions. | Their background job approach for batch destruction is practical. Destruction must be async and audited. |
| ArkCase | `document-management` | Records management lifecycle with retention schedules, disposition actions, legal holds. CMIS-compliant. | Legal hold functionality (freeze destruction during legal proceedings) is a feature we should plan for. Not in MVP but important for compliance. |
| Dimpact ZAC | `zaak-management` | Archives via OpenZaak API. ZAC itself doesn't implement archiving logic -- it delegates to OpenZaak's archivering endpoints. | Confirms that archiving should live at the register/API level (our approach), not in the case management UI layer. |
| Valtimo | `zgw-integration` | Delegates archiving to ZGW backend. Valtimo sets archiefnominatie during case completion via process tasks. | Workflow-driven archiving: set archiefnominatie as a BPMN task during case closure. Consider this pattern for our n8n integration. |

**Key takeaway:** OpenZaak's archiving model is the de facto standard. Our spec must implement the same `archiefnominatie`/`archiefactiedatum`/`archiefstatus` fields. Add: background destruction jobs (xxllnc), legal hold support (ArkCase), workflow-triggered archiving (Valtimo). Avoid: implementing archiving only in the UI layer -- it must be API-level with automated background processing.

---

## 3. avg-verwerkingsregister (OpenRegister)
**Tender demand:** 67%
**Competitors with this capability:**

| Competitor | Their spec | Approach | What we can learn |
|------------|-----------|----------|-------------------|
| Open Formulieren | `data-removal` | Per-form retention config, removal methods (delete permanently vs anonymize), sensitive field marking, BSN hashing after registration, Celery tasks for expiry. | Granular data lifecycle per form/zaaktype is essential. The `is_sensitive_data` field-level flag and automatic anonymization are patterns we should adopt. |
| ArkCase | `audit-trail` | Comprehensive audit logging but no dedicated AVG/GDPR module. Privacy is handled through access control, not a processing register. | Confirms that a dedicated verwerkingsregister is a differentiator -- most competitors don't have one. |
| Directus | `activity-revisions` | Activity log tracks all data changes with user, timestamp, IP address. Revisions allow rollback. No explicit GDPR processing register. | Activity logging is necessary but insufficient for AVG. We need a purpose-bound (doelbinding) processing register on top of audit logging. |
| NocoBase | `audit-logs` | Audit log plugin records all CRUD operations with user and timestamp. No privacy-specific features. | Same gap as others -- audit logging exists but no AVG verwerkingsregister. This is our differentiator. |
| Objects API | `history-tracking` | Record versioning with full history of all object changes. Correction mechanism for official records. | History tracking supports the "right to access" (inzagerecht) requirement. Our verwerkingsregister should integrate with object history. |
| Open Product | `audit-logging` | Comprehensive logging of all API operations with user, IP, action, and object. Time-based retention. | Good logging baseline, but no verwerkingsregister pattern. Confirms this is underserved in the ecosystem. |

**Key takeaway:** Almost no competitor has a dedicated AVG verwerkingsregister -- this is a strong differentiator. Most rely on audit logs, which track "what happened" but not "why it was processed" (doelbinding). Adopt: sensitive field marking (Open Formulieren), automatic anonymization, field-level retention policies. Build: a proper verwerkingsregister that logs processing purpose, legal basis, data categories, and recipients per processing activity. This is a unique selling point.

---

## 4. notificatie-engine (OpenRegister)
**Tender demand:** 71%
**Competitors with this capability:**

| Competitor | Their spec | Approach | What we can learn |
|------------|-----------|----------|-------------------|
| ArkCase | `notification-system` | Email-based notifications triggered by case events. Template-driven with Freemarker. Notification preferences per user. | Template-based notifications with user preferences are baseline. Consider Twig templates (consistent with our stack) instead of a separate templating engine. |
| Dimpact ZAC | `signalering-notifications` | "Signalering" system: event-driven notifications for case deadlines, assignments, status changes. Stored in DB, displayed in UI dashboard. Configurable per zaaktype and per user. | The "signalering" concept (persistent in-app notifications with dashboard) is more useful than email-only. Adopt this pattern alongside email/push. |
| Dimpact ZAC | `websocket-events` | WebSocket-based real-time event broadcasting for screen updates. SSE fallback. | Real-time push via WebSocket is expected for modern UX. We should plan for this but can use Nextcloud's existing notification infrastructure initially. |
| Flowable | `event-registry` | Event-driven architecture: events published to channels, consumed by listeners. Supports both internal events and external webhooks. Correlates events to running processes. | Event registry pattern (publish/subscribe with correlation) is architecturally superior to point-to-point notifications. Consider building our notification engine on top of an event bus. |
| Valtimo | `zgw-integration` | Notifications via Notificaties API (ZGW standard). Publishes events to kanalen, subscribers receive webhooks. | We must support the ZGW Notificaties API for interoperability. This is a hard requirement, not optional. |
| xxllnc Zaken | `communication` | Multi-channel communication: email, SMS, letter, portal messages. Template-based with merge fields. Communication history per zaak. | Multi-channel output (email + SMS + portal) with per-zaak history is the gold standard. Plan for multiple output channels from day one. |
| NocoBase | `notification-system` | Plugin-based notification system supporting in-app and email channels. Configurable triggers per collection/action. | Plugin-based extensibility for notification channels is a good pattern. |
| Directus | `notifications` | In-app notification system with user-specific inbox. API for managing notifications. | Simple in-app notification inbox. Nextcloud already provides this -- leverage it. |
| Objects API | `notifications` + `notifications-webhooks` | Webhook-based notifications on object changes. Cloud Events format. Configurable per objecttype. | Cloud Events format for webhooks is a standard we should adopt for outbound notifications. |
| Open Product | `notifications` | Notification triggers on product changes. Webhook-based with configurable endpoints. | Webhook notifications per object type change -- standard pattern, nothing unique. |
| Open Klant | `cloud-events` | Cloud Events format for publishing klantinteractie events to subscribers. | Confirms Cloud Events as the standard event format in the Dutch government ecosystem. |

**Key takeaway:** Build on three layers: (1) Nextcloud's native notification system for in-app, (2) ZGW Notificaties API for ecosystem interoperability, (3) n8n workflows for complex multi-channel routing (email, SMS, webhooks). Adopt: Cloud Events format, persistent signalering dashboard (Dimpact ZAC), multi-channel output (xxllnc). Avoid: building a custom event bus -- use n8n as the orchestration layer.

---

## 5. rapportage-bi-export (OpenRegister)
**Tender demand:** 64%
**Competitors with this capability:**

| Competitor | Their spec | Approach | What we can learn |
|------------|-----------|----------|-------------------|
| Dimpact ZAC | `dashboard-worklists` | Case management dashboards with work lists, filters, and KPI widgets. Built-in reporting views for managers. | Dashboard + worklist combo is the minimum viable reporting. Their "werklijst" pattern (filtered case list with KPIs above) should be our MVP. |
| Valtimo | `dashboard-system` | Configurable dashboard widgets: counters, charts, tables, status distributions. Widget data comes from Elasticsearch aggregations on case data. | Widget-based dashboard with Elasticsearch aggregations is powerful. Consider using our existing search infrastructure for reporting aggregations. |
| EspoCRM | `reporting-analytics` + `reports-dashboards` | Built-in report builder: filters, columns, grouping, chart types. Reports can be embedded as dashboard widgets. Scheduled report delivery via email. | Full report builder UI with drag-and-drop columns/filters is the gold standard for self-service reporting. Consider whether we build this or integrate with external BI tools. |
| Krayin | `dashboard-analytics` | Dashboard with pipeline funnel, revenue charts, activity metrics. Pre-built KPI cards. | CRM-focused analytics. Their pipeline funnel visualization is relevant for our Pipelinq reporting. |
| Twenty | `dashboard-reporting` | Custom dashboard with configurable widgets. API-first approach for data extraction. | API-first data extraction for external BI tools is a pragmatic approach for enterprise customers. |
| Directus | `insights-dashboards` | Visual dashboard builder: drag-and-drop panels (metric, bar chart, line chart, donut, table). Panels query collections with filters and aggregation. | Most polished visual dashboard builder among competitors. Their panel-type system (metric/chart/table) is a good reference for widget types. |
| NocoBase | `data-visualization` | Built-in chart plugin with multiple chart types. Data sources configurable per chart. Dashboard pages with chart grids. | Chart-per-data-source model is flexible. Their approach of embedding charts in collection pages is user-friendly. |
| Baserow | `view-types` | Views (grid, kanban, gallery, form, calendar) with filters and sorts. No native BI/reporting, but views serve as data extraction points. | View-based data organization is a precursor to reporting. Our faceted views already serve this role -- extend them with aggregation widgets. |
| Open Product | `data-export` | CSV/JSON export of product data with configurable field selection. Scheduled exports. | Basic data export is essential as a fallback when BI dashboards aren't sufficient. Ensure our API supports CSV/JSON export with field selection. |

**Key takeaway:** Two-track approach: (1) Built-in dashboard with KPI widgets and work lists for operational reporting (MVP), (2) API-based data export for integration with external BI tools (Power BI, Metabase) for advanced analytics. Adopt: widget-based dashboard (Valtimo/Directus), werklijst pattern (Dimpact ZAC), CSV/JSON export API. Avoid: building a full report builder -- that's a product in itself. Integrate with Metabase or similar instead.

---

## 6. audit-trail-immutable (OpenRegister)
**Tender demand:** 78%
**Competitors with this capability:**

| Competitor | Their spec | Approach | What we can learn |
|------------|-----------|----------|-------------------|
| ArkCase | `audit-trail` | Three-layer audit: (1) database-persisted events via AuditService, (2) Log4j2 file audit, (3) Solr-indexed audit events for search. Immutability via write-only DB table + append-only log files. | Three-layer approach (DB + file + search index) provides redundancy and query flexibility. Consider indexing audit events in our search engine for fast querying. |
| Valtimo | `audit-trail` | Event-sourced audit trail: every state change produces an immutable audit event. Events stored in a dedicated audit table. Supports replay for debugging. | Event-sourcing for the audit trail is the gold standard for immutability. Events are facts that never change. Consider this for our implementation. |
| CaseFabric | `event-sourcing-cqrs` | Full event-sourcing with CQRS: all state changes are events, the current state is a projection. Events are immutable by design. Akka-based event store. | Most architecturally pure approach. Full event-sourcing is powerful but complex. We can adopt the principle (immutable event log) without the full CQRS architecture. |
| Dimpact ZAC | `case-management` | Audit via OpenZaak's audittrail endpoint. Every zaak operation logged. Searchable via ZAC UI. | Delegates to OpenZaak's audit trail. Confirms that audit should be at the API/register level, not the application level. |
| Directus | `activity-revisions` | Activity log + revisions: every change tracked with user, timestamp, IP, action, delta. Revisions enable rollback. Stored in dedicated `directus_activity` and `directus_revisions` tables. | Delta-based revisions (storing only what changed) are storage-efficient. Rollback capability is a nice-to-have. |
| NocoBase | `audit-logs` | Audit log plugin: records all CRUD operations with user, timestamp, collection, record ID, action type. Queryable via admin interface. | Standard CRUD audit logging. Nothing unique but confirms the baseline expectation. |
| Objects API | `history-tracking` + `record-versioning-history` | Full object version history: every update creates a new version. Previous versions retrievable via API. Correction mechanism for official records. | Version-based history (each update = new version) is our existing approach in OpenRegister. Competitors confirm this is the right pattern. Add: immutability guarantees on the audit log itself. |
| Open Product | `audit-logging` | API operation logging with user, IP, action, object, timestamp. Time-based retention policies. | Retention policies on audit logs are important for storage management. Plan for configurable retention. |
| xxllnc Zaken | `background-jobs` | All events consumed by `zsnl_amqp_consumers` for audit logging to database. Event-driven architecture ensures nothing is missed. | Event-driven audit capture (consume from message queue) ensures completeness. If we adopt an event bus, audit logging should be a guaranteed consumer. |

**Key takeaway:** Immutability is the differentiator between "audit logging" and "audit trail." Most competitors log changes but don't guarantee immutability. Adopt: event-sourced append-only audit table (Valtimo/CaseFabric principle), search-indexed audit events (ArkCase), delta-based change records (Directus). Build: a dedicated immutable audit table with cryptographic chaining (hash of previous event) for tamper detection -- this exceeds what any competitor offers and is a strong compliance selling point.

---

## 7. geo-metadata-kaart (OpenRegister)
**Tender demand:** 45%
**Competitors with this capability:**

| Competitor | Their spec | Approach | What we can learn |
|------------|-----------|----------|-------------------|
| xxllnc Zaken | `geo-location` | GeoJSON storage on cases, map visualization in admin and citizen portal. Background geo-sync job for BAG/kadaster enrichment. Leaflet-based map component. | Leaflet + GeoJSON is the standard stack. Their BAG/kadaster background sync for auto-enrichment is a valuable pattern -- enrich location data automatically. |
| Objects API | `geo-search` + `geo-spatial-search` | GeoJSON geometry fields on objects. Spatial search via `geo_within` filter (point-in-polygon). PostGIS-backed. | PostGIS spatial queries are essential for "show me all cases within this area" functionality. Consider whether our MySQL/MariaDB backend can support spatial queries or if we need an alternative approach. |
| NocoBase | `map-fields` | Dedicated map field type: stores coordinates, renders on map. Amap/Google Maps integration. Geo-search within radius. | Map field type as a first-class schema field type is a clean model. Consider adding a `geo` field type to OpenRegister schemas. |
| CKAN | `search-faceting` | Spatial search via Solr spatial extensions. Bounding-box and point-radius search on datasets with geographic metadata. | Spatial faceting (filter by bounding box) is useful for map-based discovery. |
| Directus | `data-modeling` | Geometry field type with point/line/polygon support. Map interface for visual editing. | Geometry field type with visual editor for drawing polygons on a map. Good UX pattern. |
| Baserow | `field-types` | No native geo fields. Geo support would require custom field type. | Gap in Baserow -- confirms that geo is a differentiator for data platforms. |

**Key takeaway:** Leaflet + GeoJSON is the consensus stack. MySQL lacks PostGIS -- we need either Elasticsearch spatial queries or a dedicated geo service. Adopt: GeoJSON geometry field type (Objects API), BAG auto-enrichment (xxllnc), Leaflet map visualization. Challenge: our MySQL/MariaDB backend doesn't natively support spatial queries like PostGIS. Plan for Elasticsearch/Solr spatial indexing as the query backend.

---

## 8. workflow-integration (OpenRegister)
**Tender demand:** 82%
**Competitors with this capability:**

| Competitor | Their spec | Approach | What we can learn |
|------------|-----------|----------|-------------------|
| Flowable | `bpmn-engine` + `cmmn-case-management` | Full BPMN 2.0 + CMMN 1.1 engines. Java-native, command-pattern execution. Process/case definitions deployed as XML. Rich REST API. | The gold standard for workflow engines. Their dual BPMN+CMMN approach (structured processes + dynamic cases) is the reference architecture. We integrate via n8n rather than embedding a Java engine. |
| Valtimo | `process-engine` | Camunda 7 (forked) BPMN engine wrapped with ZGW-specific service tasks. Kotlin connectors for ZGW APIs. Process-linked forms. | Valtimo wraps Camunda with government-specific plugins. Their approach of pre-built ZGW service tasks (create zaak, update status, send document) is what we should replicate as n8n nodes. |
| CaseFabric | `cmmn-engine` | Custom CMMN engine built on Akka actors. Event-sourced case lifecycle. Discretionary items for ad-hoc tasks. | Actor-based CMMN is unique but non-standard. Their discretionary items (ad-hoc tasks that case workers can choose to perform) is a good UX concept for our case management. |
| Dimpact ZAC | `workflow-engine-integration` + `process-automation` | CMMN via Flowable (embedded). BPMN for structured sub-processes. Admin-configurable per zaaktype. Process definitions linked to case handling parameters. | ZAC's approach of linking CMMN definitions to zaaktype configuration is exactly our model. Their admin UI for mapping process definitions to zaaktypen is a reference. |
| ArkCase | `workflow-engine` | Activiti-based BPMN engine. Workflow triggered by case events. Business process rules via Drools. | Activiti (predecessor of Flowable) + Drools rules engine. Confirms BPMN as the standard. Drools for business rules is heavy -- we use n8n's logic capabilities instead. |
| Baserow | `automations` | No-code automation builder: triggers (row created/updated, scheduled) + actions (update row, send email, webhook). Visual builder UI. | Baserow's no-code automation builder is conceptually similar to n8n. Their trigger-action model is simpler but less powerful. Confirms that visual workflow building is expected. |
| Directus | `flows-automation` | Visual flow builder: triggers + operations. Supports webhooks, conditions, data manipulation. Built into admin UI. | Directus Flows are similar to n8n workflows but embedded in the platform. Confirms the pattern of visual workflow building. |
| NocoBase | `workflow-engine` | Built-in workflow engine with visual builder. Triggers on CRUD events. Action nodes: create/update records, send requests, conditions. Approval workflow plugin. | NocoBase has a built-in approval workflow -- relevant for our B&W parafering. Their approach of CRUD-triggered workflows is similar to our schema hooks + n8n. |
| Strapi | `review-workflows` | Content review workflows with configurable stages and role-based transitions. | Simple stage-based workflow for content review. Too limited for case management but the concept of configurable stages per content-type is relevant. |
| EspoCRM | `bpm-workflow-engine` | BPMN-like visual process builder. Event-based triggers. Conditions, actions, user tasks, send-message tasks. | EspoCRM built a simplified BPM into a CRM. Their UX for non-technical users to define workflows is a good reference. |
| xxllnc Zaken | `process-builder` | Visual process builder for case workflows. Steps, conditions, timers, document generation. Linked to zaaktypen. | Their process-builder is tightly coupled to case management -- a simpler alternative to full BPMN. Each step maps to a case status transition. |

**Key takeaway:** We're the only competitor using n8n as the workflow engine -- this is both a risk (non-standard) and an opportunity (more flexible, lower barrier). All others use embedded BPMN/CMMN engines. Our n8n approach is viable if we build government-specific n8n nodes (create zaak, update status, send notification, generate document) that match the pre-built service tasks in Valtimo/Flowable. Adopt: pre-built government workflow templates, zaaktype-to-workflow mapping. Avoid: trying to be BPMN-compliant -- n8n is a different paradigm and that's fine.

---

## 9. zoeken-filteren (OpenRegister)
**Tender demand:** 89%
**Competitors with this capability:**

| Competitor | Their spec | Approach | What we can learn |
|------------|-----------|----------|-------------------|
| ArkCase | `search-engine` | Solr-based full-text search with faceting. Access-control-aware (queries filtered by user permissions). Saved searches. | Access-control-aware search (inject permission filters into every query) is critical. Our search must enforce RBAC at query time, not post-filter. |
| Dimpact ZAC | `search-and-indexing` | Solr-based search with zaak/document indexing. Faceted search on zaaktype, status, behandelaar. Full-text search on document content. | Document content indexing (extracting text from PDFs for search) is expected. Plan for Tika/similar content extraction in our indexing pipeline. |
| CKAN | `search-faceting` | Solr-based search with rich faceting: multi-select facets, date-range facets, spatial facets. Customizable facet rendering. | CKAN's faceting is the most mature. Multi-select facets (select multiple values within one facet) and date-range facets are must-haves. |
| Baserow | `search-export` | PostgreSQL `tsvector` full-text search. Multiple search modes. Background index updates via tasks. CSV export with view filters applied. | PostgreSQL-native search is simpler to operate than Solr/Elasticsearch. Consider dual-mode: built-in DB search for simple cases, Elasticsearch for advanced. |
| PocketBase | `search-filtering` | Filter syntax with operators (`=`, `!=`, `>`, `~`, `?~`). Supports nested field access and relation traversal. Sort by any field. | PocketBase's filter syntax is developer-friendly. Our API filter syntax should be at least as expressive. |
| Objects API | `data-filtering` + `data-attribute-filtering` | JSON path-based filtering on object properties. Supports nested attribute filtering (`data_attrs=key__exact__value`). Ordering by any field. | JSON attribute filtering is essential for our schema-less objects. The `data_attrs` filter pattern is the ZGW standard we must support. |
| Directus | `insights-dashboards` | Aggregation queries via API: count, sum, avg, min, max with grouping. Used for both dashboards and filtered views. | API-level aggregation (not just filtering) is important for reporting. Our search API should support aggregation queries. |
| NocoBase | `data-visualization` | Filterable data views with multiple chart types. Aggregation-based visualizations. | Confirms that search + aggregation + visualization is a continuum, not separate features. |
| PocketBase | `view-collections` | Virtual collections defined by SQL queries. Real-time updated. Read-only but queryable. | Virtual views (computed collections from queries) are powerful for cross-schema reporting. Consider supporting saved views/filters. |

**Key takeaway:** Full-text search with faceting is table stakes. Our existing Elasticsearch/Solr integration covers this. Differentiators: (1) RBAC-aware search, (2) document content extraction, (3) JSON attribute filtering (ZGW-compatible), (4) aggregation queries for reporting. Adopt: access-control-aware queries (ArkCase), multi-select facets (CKAN), `data_attrs` filter syntax (Objects API). Already strong: our faceting-configuration spec is ahead of most competitors.

---

## 10. document-zaakdossier (OpenRegister)
**Tender demand:** 76%
**Competitors with this capability:**

| Competitor | Their spec | Approach | What we can learn |
|------------|-----------|----------|-------------------|
| OpenZaak | `documenten-api` | ZGW Documenten API: CRUD for informatieobjecten. Documents stored in DRC (Documenten Registratie Component). Linked to zaken via `zaakinformatieobject`. Supports `bestandsdelen` for chunked upload. Versioning via `lock`/`unlock`. | The reference API. We must be ZGW Documenten API compatible. Their `bestandsdelen` chunked upload and lock/unlock versioning are essential. |
| ArkCase | `document-management` | CMIS-compliant document management. Alfresco integration. Version history, check-in/check-out, metadata extraction, PDF conversion. | CMIS is the enterprise standard for document management. Not relevant for our stack (we use Nextcloud Files), but confirms that version history + metadata extraction are expected. |
| Dimpact ZAC | `document-management` + `smart-documents-integration` | Documents managed via OpenZaak's Documenten API. SmartDocuments integration for template-based document creation. Document preview via OnlyOffice/Collabora. | SmartDocuments integration is a common tender requirement. Our Docudesk template-based generation is the equivalent. Document preview (OnlyOffice/Collabora) is expected -- Nextcloud provides this natively. |
| Valtimo | `document-generation` | Template-based document generation using Freemarker templates. Merge case data into templates. Output to DRC via Documenten API. | Same pattern as our document-creatie-sjablonen spec. They use Freemarker, we use Twig. Both merge case data into templates. |
| xxllnc Zaken | `document-management` | Document upload, metadata tagging, version history, virus scanning, PDF preview. Documents stored in S3-compatible storage. Linked to cases. | Virus scanning on upload is a requirement we should address. Nextcloud already has ClamAV integration. Confirm it's active for document uploads. |
| CaseFabric | `case-file-management` | Case file (zaakdossier) as a structured container: documents organized by category within a case. Document lifecycle tied to case lifecycle. | "Case file" as a structured folder concept (not just flat document list) is important. Documents should be categorizable within a zaak dossier (e.g., "aanvraag", "advies", "besluit"). |
| Directus | `file-management` | File storage with S3/local backend. Image transformations. Metadata extraction. Folder organization. | Metadata extraction from uploaded files (EXIF, PDF metadata) is a useful automation. |
| NocoBase | `file-management` | Attachment field type, multiple storage backends (local, S3, Tencent COS). File preview. | Multiple storage backends confirm that abstracted file storage is expected. Nextcloud handles this. |

**Key takeaway:** We have a strong position here: Nextcloud Files provides versioning, preview (OnlyOffice/Collabora), virus scanning (ClamAV), and storage. We need: (1) ZGW Documenten API compatibility, (2) structured dossier organization (categorized documents within a zaak), (3) metadata extraction on upload, (4) chunked upload for large files. Adopt: case-file-as-structured-container (CaseFabric), document category tagging. Already strong: Nextcloud's native document management exceeds most competitors.

---

## 11. zaak-intake-flow (Procest)
**Tender demand:** 61%
**Competitors with this capability:**

| Competitor | Their spec | Approach | What we can learn |
|------------|-----------|----------|-------------------|
| Dimpact ZAC | `productaanvraag-intake` | Productaanvraag pattern: Open Formulieren submits a productaanvraag object to Objects API, ZAC polls for new productaanvragen, creates zaak + documents + initiator role automatically. Configurable per zaaktype via `productaanvraagtype` mapping. | The productaanvraag pattern is the de facto standard for form-to-case intake in Dutch government. We must support this exact flow. Their polling approach is less elegant than webhooks -- use our n8n webhook triggers instead. |
| Open Formulieren | `registration-backends` | Plugin-based registration: after form submission, data is pushed to a backend (ZGW zaak creation, Objects API, StUF-ZKN, email). Supports multiple backends per form with logic-based routing. | Multi-backend registration with conditional routing is powerful. A form can create a zaak AND send an email AND push to Objects API. Consider supporting multiple intake actions per submission. |
| OpenZaak | `zaak-lifecycle` | Zaak creation via `POST /zaken/api/v1/zaken`. Validates against zaaktype. Auto-sets startdatum. Creates initial status. Links initiator rol. | Reference API for zaak creation. Our intake must produce ZGW-compliant zaak objects. |
| xxllnc Zaken | `citizen-portal` | Citizen-facing portal for case submission. Portal-to-case pipeline with auto-zaaktype assignment based on product category. | Citizen portal with product-based zaaktype routing is a pattern we should support (via Open Formulieren integration). |
| Flowable | `form-engine` | Form definitions deployed alongside processes. Form submission triggers process start. Form data mapped to process variables. | Form-to-process trigger pattern. Our equivalent: Open Formulieren submission triggers n8n workflow which creates zaak. |
| Valtimo | `form-system` | FormIO-based forms linked to process start events. Form submission creates a case document (JSON in the case file). | FormIO integration for case intake. Confirms that form-engine integration is expected, not custom form building. |
| CaseFabric | `case-modeling` | Case instantiation from case model definition. Initial stage, plan items, and sentries activated automatically. | CMMN-based case instantiation with automatic plan item activation. Our n8n-based post-intake workflow achieves similar automation. |

**Key takeaway:** The productaanvraag pattern (Open Formulieren -> Objects API -> case system) is the standard we must support. Adopt: productaanvraag-based intake (Dimpact ZAC), multi-backend registration (Open Formulieren), ZGW-compliant zaak creation. Our n8n-based intake processing can exceed competitors by being more flexible (custom logic per zaaktype).

---

## 12. vth-module (Procest)
**Tender demand:** 44%
**Competitors with this capability:**

| Competitor | Their spec | Approach | What we can learn |
|------------|-----------|----------|-------------------|
| Dimpact ZAC | `decision-management` | Decision (beschikking) management: create besluit linked to zaak, set resultaattype, manage bezwaartermijn. Integrated with document generation for beschikkingen. | Besluit management with bezwaartermijn tracking is essential for VTH. Our spec should cover the full besluit lifecycle: concept -> definitief -> bekendmaking -> bezwaartermijn -> onherroepelijk. |
| Dimpact ZAC | `case-management` + `zaak-management` | Case management with VTH-specific fields: einddatum, fatale datum, uitstel. Multi-case view for comparing related cases. | Fatale datum (legal deadline) tracking with visual warnings is critical for VTH. Uitstel (deadline extension) must be audited. |
| OpenZaak | `catalogi-zaaktypen` | Zaaktype catalog with VTH-specific properties: vertrouwelijkheidaanduiding, doorlooptijd, servicenorm, archiefnominatie, besluittypen, roltypen. | The zaaktype catalog must support VTH-specific configuration: which besluittypen are allowed, which roltypen exist, doorlooptijd and service norms. |
| xxllnc Zaken | `rule-engine` | Business rules for automated decisions: conditions evaluated against case data to determine outcomes. Supports if/then/else chains. | Rule-based decision support for VTH assessments (e.g., "if bouwkosten > 50000 AND monument = true THEN advies_vereist = true"). Consider n8n-based business rules. |
| Flowable | `dmn-decision-engine` | DMN (Decision Model and Notation) engine for business rules. Decision tables with input/output columns. Visual editor. | DMN is the standard for business rules in case management. Our n8n-based approach is less formal but more accessible. For VTH compliance, consider supporting DMN table imports. |
| Valtimo | `zgw-integration` | ZGW-integrated VTH: zaaktype configuration, status flows, besluit creation, document generation for beschikkingen. | End-to-end VTH flow within ZGW ecosystem. Confirms our approach of building on ZGW standards. |

**Key takeaway:** VTH is a specialized vertical on top of case management. Key additions: besluit lifecycle management, fatale datum tracking, business rules for assessment support, DSO/Omgevingsloket integration (covered by our dso-omgevingsloket spec). No single competitor covers VTH end-to-end -- it's assembled from components. Our integrated approach (Procest + OpenRegister + OpenConnector) can be a differentiator.

---

## 13. legesberekening (Procest)
**Tender demand:** 32%
**Competitors with this capability:**

| Competitor | Their spec | Approach | What we can learn |
|------------|-----------|----------|-------------------|
| xxllnc Zaken | `payments` | Worldline payment gateway integration. Payment linked to case. Status tracking (started/processing/completed). Webhook-based status updates. | Payment gateway integration is separate from leges calculation. xxllnc has payment but not automated legesberekening -- they expect manual entry. |
| Open Formulieren | `payment-processing` | Ogone/Worldline payment plugins. Price calculation from form logic or product catalog. Payment status tracked per submission. | Dynamic price calculation via form logic is a pattern for simple leges. For complex legesberekening (tarieventabel + bouwkosten + activiteiten), we need more. |
| Open Product | `pricing-engine` | Product pricing with tariffs, date-based validity, and DMN integration for dynamic pricing. Supports complex pricing rules. | Open Product's DMN-based pricing engine is the closest match. Consider integrating with or replicating their tariff-table + calculation-rule pattern. |
| Flowable | `dmn-decision-engine` | DMN tables for decision rules. Can be used for tariff calculation by modeling leges tables as DMN decision tables. | DMN tables are ideal for modeling legestarieven: input columns (activiteit, bouwkosten, categorie), output column (legesbedrag). |
| Dimpact ZAC | `decision-management` | Besluit management but no built-in legesberekening. Leges handled externally. | Gap in ZAC -- confirms legesberekening is underserved and a differentiator. |

**Key takeaway:** Legesberekening is a rare capability -- most competitors don't have it. This is a differentiator. Adopt: tariff-table model (Open Product), DMN-based calculation rules (Flowable). Build: configurable legestarieven per zaaktype, automatic calculation from DSO-verzoek data (bouwkosten, activiteiten), integration with payment gateway for direct leges betaling. The combination of automated calculation + payment integration is unique in the market.

---

## 14. bw-parafering (Procest)
**Tender demand:** 38%
**Competitors with this capability:**

| Competitor | Their spec | Approach | What we can learn |
|------------|-----------|----------|-------------------|
| ArkCase | `case-management` | Approval chains on case artifacts. Multi-level approval with delegation. Approver comments and conditions. | Multi-level approval with delegation is essential. Delegation (when the portefeuillehouder is on leave) must be supported. |
| CaseFabric | `human-task-workflow` + `process-tasks` | Human task workflows with approval steps. Tasks assigned to roles, not individuals. Multi-step approval chains configurable per case type. | Role-based assignment (not person-based) is correct for parafering routes. The paraaf goes to the "portefeuillehouder" role, resolved to a person at runtime. |
| Dimpact ZAC | `task-management` | Task assignment to users/groups. No dedicated parafering workflow -- handled via generic task management. | ZAC lacks dedicated B&W parafering. This is a gap we can exploit. |
| NocoBase | `workflow-engine` | Approval workflow plugin with configurable approval chains. Sequential and parallel approval. Approve/reject/return actions. | NocoBase's approval workflow plugin is the closest to what we need architecturally. Sequential + parallel approval modes, configurable per content type. |
| Flowable | `task-service` | Human task management with claim, delegate, complete operations. Task forms for approval input. Priority and due-date management. | Task lifecycle (unclaimed -> claimed -> completed) with delegation is the standard model. Our parafering tasks should follow this lifecycle. |
| Valtimo | `task-management` | Task management with user/group assignment, delegation, due dates. Forms attached to tasks for collecting approval input. | Same pattern as Flowable tasks. Confirms the claim/delegate/complete lifecycle. |
| xxllnc Zaken | `task-management` | Task management with assignment and deadline tracking. No specific B&W parafering module. | Another competitor without dedicated parafering. Confirms our differentiator. |

**Key takeaway:** B&W parafering as a dedicated module is rare -- most competitors handle it as generic task management. Our spec's explicit parafering route (steller -> adviseur -> parafeerder -> portefeuillehouder -> secretariaat -> B&W) with iBabs/NotuBiz integration (from our ibabs-notubiz-connector spec) is unique. Adopt: role-based assignment (CaseFabric), delegation support (ArkCase), approve/reject/return actions (NocoBase). Build: pre-configured parafering route templates per zaaktype, mobile-friendly approval UI, iBabs/NotuBiz handoff.

---

## 15. werkvoorraad (Procest)
**Tender demand:** 85%
**Competitors with this capability:**

| Competitor | Their spec | Approach | What we can learn |
|------------|-----------|----------|-------------------|
| Dimpact ZAC | `dashboard-worklists` | Werklijsten (work lists) as the primary navigation paradigm. Configurable columns, filters per zaaktype. Personal and team werklijsten. Sortable by deadline, priority. Color-coded deadline warnings. | ZAC's werklijsten are the benchmark. Configurable columns per zaaktype, color-coded deadlines (green/yellow/red), and the split between personal and team queues is the expected UX. |
| ArkCase | `task-management` | Task queues with claimed/unclaimed split. Queue management for team leads. Task routing rules. | Claimed/unclaimed split and queue management for team leads are important patterns. The team lead should be able to see and redistribute the team's work. |
| Flowable | `task-service` | Task inbox with query capabilities. Filter by process, assignee, candidate group, priority, due date. Pagination and sorting. | Rich task query API with multiple filter dimensions. Our API must support the same query flexibility. |
| Valtimo | `task-management` | Case list and task list as separate views. Task assignment via claim. Bulk actions (reassign, complete). | Bulk actions (reassign multiple tasks at once) are important for team leads managing staff changes. |
| xxllnc Zaken | `task-management` | Task overview with filtering by status, assignee, zaaktype, deadline. Task counters per category in sidebar. | Task counters in the sidebar navigation (e.g., "Mijn taken (12)", "Team Vergunningen (45)") provide at-a-glance workload visibility. |
| CaseFabric | `human-task-workflow` | Task list with plan-item-based tasks. Dynamic task creation based on case progression. | Dynamic task creation (tasks appear as the case progresses) is the CMMN approach. Our n8n workflows create tasks dynamically -- same outcome. |
| Krayin | `activities` | Activity tracking with tasks, calls, meetings, deadlines. Calendar integration. | Activity-based work tracking (not just tasks) is broader. Consider integrating with Nextcloud Calendar for deadline visualization. |

**Key takeaway:** Werkvoorraad is the most demanded feature and all competitors have it. The bar is high. Must-haves: personal + team queues, configurable columns, color-coded deadlines, bulk actions, task counters. Differentiator: Nextcloud integration (tasks linked to Calendar, notifications via NC notification system, files visible in NC Files). Adopt: werklijst paradigm (Dimpact ZAC), bulk actions (Valtimo), counter badges (xxllnc).

---

## 16. mobiel-inspectie (Procest)
**Tender demand:** 27%
**Competitors with this capability:**

| Competitor | Their spec | Approach | What we can learn |
|------------|-----------|----------|-------------------|
| xxllnc Zaken | `citizen-portal` | Responsive citizen portal usable on mobile. Not a dedicated inspection tool but demonstrates mobile-first web design. | Mobile-first responsive design, not a native app. Confirms PWA approach is acceptable. |
| ArkCase | `case-management` | No dedicated mobile inspection module. Mobile access via responsive web UI. | Gap in ArkCase -- confirms mobile inspection is a differentiator. |
| Dimpact ZAC | `case-management` | No dedicated mobile inspection. ZAC is desktop-focused. | Major gap in ZAC. Their desktop-first approach is a weakness we can exploit for VTH. |
| Flowable | `rest-api` | Full REST API enables mobile client development. No built-in mobile inspection UI. | API-first approach enables custom mobile clients. Our REST API must be mobile-friendly (offline-sync-ready). |

**Key takeaway:** Mobile inspection is a greenfield -- no competitor has a dedicated solution. This is a strong differentiator. Build: PWA with offline capability (inspection checklists cached locally, photo capture, GPS location, sync when online), Nextcloud Files integration for photo uploads, inspection checklist templates per zaaktype. The combination of mobile + offline + photo + GPS is unmatched.

---

## 17. zaaktype-configuratie (Procest)
**Tender demand:** 79%
**Competitors with this capability:**

| Competitor | Their spec | Approach | What we can learn |
|------------|-----------|----------|-------------------|
| OpenZaak | `catalogi-zaaktypen` | ZGW Catalogi API: zaaktypen with statustypen, resultaattypen, roltypen, besluittypen, eigenschappen, informatieobjecttypen. Full lifecycle management with versioning. | The reference implementation. Our zaaktype configuration must be ZGW Catalogi API compatible. Their versioning (concept -> definitief -> deprecated) is the standard. |
| Dimpact ZAC | `admin-configuration` | Zaakafhandelparameters per zaaktype: default behandelaar, default group, CMMN case definition, intake/afronding mail toggles, form definitions, process definitions. | ZAC's approach of layering "handling parameters" on top of ZGW zaaktypen is exactly right. The zaaktype defines WHAT, the afhandelparameters define HOW. Adopt this two-layer model. |
| xxllnc Zaken | `catalog-admin` | Visual catalog management UI. Zaaktypen with steps, forms, documents, deadlines. Drag-and-drop step ordering. | Visual step ordering with drag-and-drop is a good UX for configuring process steps per zaaktype. |
| Open Beheer | `zaaktype-management` + `informatieobjecttype-management` + `version-management` | Dedicated admin UI for zaaktype catalog management. CRUD for zaaktypen, informatieobjecttypen, and related types. Version management with concept/definitief states. | Open Beheer is a standalone admin tool for ZGW catalog management. Confirms that a dedicated admin UI for zaaktype configuration is expected (not just API-level management). |
| Valtimo | `case-management` | Case definitions with tabs, pre-conditions, widgets. Case type imports/exports for migration between environments. | Import/export of case type definitions for environment migration (dev -> test -> prod) is critical for maintainability. Ensure our zaaktype config is exportable/importable. |
| CaseFabric | `case-modeling` | Visual case model designer (CMMN-based). Stages, plan items, sentries modeled visually. | Visual case model designer is aspirational but valuable. For MVP, form-based configuration suffices. |

**Key takeaway:** Two-layer model: (1) ZGW-compatible zaaktype catalog (what types exist), (2) handling parameters per zaaktype (how each type is handled). Adopt: afhandelparameters pattern (Dimpact ZAC), import/export for migration (Valtimo), visual step ordering (xxllnc). Must: ZGW Catalogi API compatibility for interoperability.

---

## 18. case-dashboard-view (Procest)
**Tender demand:** 72%
**Competitors with this capability:**

| Competitor | Their spec | Approach | What we can learn |
|------------|-----------|----------|-------------------|
| Dimpact ZAC | `case-management` + `zaak-management` | Zaak detail view: header with status/type/deadlines, tabs for documenten/historie/betrokkenen/taken/notities, inline status updates, linked zaken sidebar. | ZAC's zaak detail view is the reference. Tabbed layout with status header + document/task/history tabs is the expected pattern. |
| Valtimo | `dashboard-system` + `case-management` | Configurable case detail with tabbed panels, widgets (status, milestones, documents, tasks, timeline). Widget configuration per case type. | Configurable widget layout per case type is powerful. Different zaaktypen show different widgets. |
| ArkCase | `case-management` | Case detail with comprehensive view: parties, documents, tasks, correspondence, billing, notes, history. Collapsible panels. | ArkCase's case view is the most comprehensive but also the most cluttered. Confirm that progressive disclosure (collapsible panels, tabs) prevents information overload. |
| xxllnc Zaken | `case-management` | Case detail with timeline, document list, task list, metadata panel, communication history. Responsive layout. | Timeline-first approach (chronological view of all case events) is intuitive for case workers. |
| CaseFabric | `case-file-management` | Case file view: structured document tree, task list, milestone progress bar, case team members. | Milestone progress bar (visual indicator of how far along the case is) is a great UX element. |
| Valtimo | `milestone-tracking` | Milestone progress visualization: ordered milestones with completed/current/upcoming states. Color-coded progress bar. | Milestone tracking provides a business-friendly view of case progress. Map our zaak statussen to a visual progress bar. |
| Flowable | `cmmn-case-management` | Case overview with plan items (tasks), milestones, stages, documents, sub-processes. Visual case plan. | Visual case plan showing stages and items is advanced CMMN visualization. Aspirational for our case dashboard. |

**Key takeaway:** Case dashboard is the single most-used screen -- it must be excellent. Must-haves: status header with deadline visualization, tabbed content (documenten/taken/historie/notities), milestone progress bar, timeline. Differentiator: Nextcloud integration (documents visible in NC Files, tasks in NC tasks, timeline includes NC activity). Adopt: configurable widgets per zaaktype (Valtimo), milestone progress bar (CaseFabric/Valtimo), timeline-first approach (xxllnc).

---

## 19. kcc-werkplek (Pipelinq)
**Tender demand:** 100%
**Competitors with this capability:**

| Competitor | Their spec | Approach | What we can learn |
|------------|-----------|----------|-------------------|
| KISS | `contactmoment-logging` + `contactmomenten` + `persoon-search` + `bedrijf-search` + `kanalen-channels` + `zaaksysteem-integration` + `werkberichten-news` | Full KCC workspace: concurrent contactmoment handling (session isolation per tab), BRP/KVK person search, zaak linking, knowledge base (Elasticsearch), werkberichten news feed, multi-question per contactmoment. Vue 3 + Pinia frontend. | KISS is the direct competitor for KCC werkplek. Key learnings: (1) Session isolation per contactmoment (crucial for handling multiple calls), (2) multi-question model (one contact can contain multiple questions), (3) werkberichten (internal news for agents). |
| Open Klant | `klantcontacten` + `klantinteracties-api` + `maak-klantcontact` + `interne-taken` + `partijen` + `betrokkenen` + `actoren` | VNG Klantinteracties API implementation: klantcontacten, partijen (persoon/organisatie), betrokkenen, actoren (medewerkers), interne taken, onderwerpobjecten. Standards-compliant data model. | Open Klant provides the API standard we must be compatible with. Their data model (Partij, Betrokkene, Klantcontact, Actor, InterneTaak) is the VNG reference. |
| Open VTB | `taken` + `verzoeken` + `berichten` + `formuliertaak` | Citizen task portal: taken (tasks for citizens), berichten (messages from/to citizens), formuliertaak (form-based tasks). Complementary to KCC werkplek (this is the citizen side). | Open VTB is the citizen-facing complement. Our KCC werkplek should be able to create taken that appear in the citizen's VTB portal. Plan for this integration. |
| Erxes | `omnichannel-inbox` | Unified inbox across email, chat, social media, phone. Conversation threading. Agent assignment and transfer. Real-time updates. | Erxes's omnichannel inbox is the most polished multi-channel UI. Their conversation threading and real-time agent status are UX references. |

**Key takeaway:** KISS is the primary competitor and sets the standard. We must match: concurrent contactmoment handling, BRP/KVK search, zaak linking, knowledge base access. We can exceed: Nextcloud-native integration (Calendar, Files, Contacts, Mail), offline PWA capability, n8n-powered automation. Adopt: session isolation model (KISS), VNG Klantinteracties data model (Open Klant), omnichannel UX patterns (Erxes). Must: VNG Klantinteracties API compatibility.

---

## 20. klantbeeld-360 (Pipelinq)
**Tender demand:** 83%
**Competitors with this capability:**

| Competitor | Their spec | Approach | What we can learn |
|------------|-----------|----------|-------------------|
| KISS | `persoon-search` + `contactmoment-logging` | Person search via BRP/KVK, then view linked contactmomenten and zaken. Basic klantbeeld but not a dedicated 360 view. | KISS provides the building blocks but not a unified 360 view. Our dedicated klantbeeld-360 page is a differentiator. |
| Open Klant | `partijen` + `klantcontacten` + `betrokkenen` + `contactgegevens` + `digitale-adressen` | Partij (person/organization) as the central entity with linked klantcontacten, betrokkenen (case roles), contactgegevens, digitale adressen. | Open Klant's Partij model is the data foundation. Our klantbeeld aggregates data from Partij + external sources (BRP, KVK, ZGW) into a single view. |
| Monica | `contact-management` + `relationships` + `journal-timeline` + `notes-documentation` + `life-events` | Personal CRM: comprehensive contact profiles with relationships, timeline of interactions, notes, life events (birthdays, jobs, addresses). | Monica's approach of tracking life events and relationships is richer than government CRM typically requires, but the timeline-first UX and relationship mapping are excellent patterns. |
| EspoCRM | `data-model` + `reporting-analytics` | Contact/Account entities with 360 views: linked activities, opportunities, cases, documents, emails. Panel-based layout. | EspoCRM's panel-based 360 view (contact header + panels for each linked entity type) is the standard CRM pattern. |
| Twenty | `data-model` + `activity-tracking` | Person/Company records with activity timeline, tasks, notes, emails. Customizable record detail layout. | Twenty's customizable record layout (choose which panels appear and in what order) is a nice personalization feature. |
| Erxes | `contacts-crm` | Contact profiles with conversation history, tags, internal notes, custom properties. Omnichannel interaction history. | Omnichannel conversation history (see all chats, emails, calls in one timeline) is essential for government klantbeeld. |

**Key takeaway:** Our klantbeeld-360 as a dedicated aggregated view is a differentiator -- KISS doesn't have it, Open Klant provides the API but not the UI. Build: timeline-first 360 view (Monica pattern), BRP/KVK enrichment on demand with AVG-logged access, panel-based layout with contactmomenten + zaken + documenten + notes. Must: doelbinding logging for every data access (AVG requirement unique to government).

---

## 21. contactmomenten-rapportage (Pipelinq)
**Tender demand:** 98%
**Competitors with this capability:**

| Competitor | Their spec | Approach | What we can learn |
|------------|-----------|----------|-------------------|
| KISS | `contactmoment-logging` | Contactmoment data is logged but KISS has no built-in reporting/analytics dashboard. Reporting relies on external tools. | Gap in KISS -- they have the data but not the analytics. Our built-in rapportage is a strong differentiator. |
| EspoCRM | `reporting-analytics` + `reports-dashboards` | Built-in report builder with filters, grouping, chart types (bar, pie, line). Reports embeddable as dashboard widgets. Scheduled email delivery. | Most complete built-in reporting among CRM competitors. Their report builder UX (select entity -> add filters -> choose grouping -> select chart) is a good reference. |
| Krayin | `dashboard-analytics` | Dashboard with KPI cards, pipeline funnel, revenue/activity charts. Pre-built analytics widgets. | Pre-built KPI cards (total contacts, average handling time, resolution rate) should be our MVP -- not a custom report builder. |
| Twenty | `dashboard-reporting` | Configurable dashboard with widgets. API-driven data for external BI integration. | API-first reporting for BI tool integration is the enterprise approach. Support both built-in dashboards and API export. |
| Directus | `insights-dashboards` | Drag-and-drop dashboard builder with metric/chart/table panels. Each panel queries a collection with filters and aggregation. | Directus's panel-based dashboard builder is the most polished UX for creating custom dashboards. |

**Key takeaway:** KISS (our direct competitor) has no reporting -- this is a major gap we exploit. Our contactmomenten-rapportage spec directly addresses the #2 most demanded capability (98%). Build: pre-built KPI dashboard (Krayin simplicity), configurable widgets (Directus flexibility), CSV/API export for BI tools. Don't overbuild -- start with pre-defined KPIs, add custom report building later.

---

## 22. omnichannel-registratie (Pipelinq)
**Tender demand:** 54%
**Competitors with this capability:**

| Competitor | Their spec | Approach | What we can learn |
|------------|-----------|----------|-------------------|
| KISS | `kanalen-channels` + `contactmoment-logging` | Channel selection during contactmoment registration. Supports telefoon, balie, e-mail. Channel stored as field on contactmoment. Basic metadata per channel. | KISS has basic channel support but limited channel-specific metadata. Our spec extends this with rich per-channel metadata objects. |
| Open Klant | `klantcontacten` + `categorieen` | Klantcontact with kanaal field (telefoon, email, chat, etc.). Categorieen for classifying contact type. | VNG standard kanaal values. We must use the same channel vocabulary for interoperability. |
| Erxes | `omnichannel-inbox` | True omnichannel: email, chat, Facebook, Twitter, SMS, WhatsApp, phone. Each channel has a dedicated integration. Unified inbox merges all channels. | Most comprehensive omnichannel. Their per-channel integrations (not just metadata, but actual channel connectivity) is aspirational. For MVP, we register contacts from all channels; for V1, we integrate with actual channels. |
| Krayin | `email` + `web-forms` | Email integration (IMAP/SMTP) and web form capture as contact channels. | Email integration as a channel (not just registering email contacts, but actually reading/sending from within the CRM) is a V1 feature. |
| EspoCRM | `email-integration` | Full email integration: IMAP polling, SMTP sending, email-to-entity linking. Email conversations visible on contact records. | Email-as-first-class-channel with inbox integration. Nextcloud Mail could provide this for our platform. |

**Key takeaway:** Channel-specific metadata (our approach) is more structured than competitors who just store a channel name string. Adopt: VNG standard channel vocabulary (Open Klant), email integration via Nextcloud Mail. Differentiator: our per-channel metadata schema (duration for phone, thread-ID for email, location for balie) enables richer analytics. Plan for V1: actual channel integrations (Nextcloud Mail, chat platform connectors).

---

## 23. kennisbank (Pipelinq)
**Tender demand:** ~74% (implicit via FCR targets)
**Competitors with this capability:**

| Competitor | Their spec | Approach | What we can learn |
|------------|-----------|----------|-------------------|
| KISS | `elasticsearch-knowledge-base` | Elasticsearch-backed knowledge base: articles indexed and searchable during contactmoment handling. Articles displayed as search results within the contactmoment workflow. Usefulness feedback per article. | KISS's knowledge base is tightly integrated into the contactmoment flow -- agents search articles while on a call. This in-context search is critical. Adopt: article search embedded in the contactmoment UI, usefulness feedback loop. |
| KISS | `werkberichten-news` | Internal news feed (werkberichten) for KCC agents: announcements, policy updates, operational messages. Separate from knowledge base articles. | Werkberichten (operational announcements) complement the knowledge base. Consider a "news" section alongside the article repository. |
| Open Klant | `categorieen` | Category system for organizing klantcontacten by subject. Hierarchical categories. | Category taxonomy for organizing knowledge articles and contact moments. Use the same taxonomy for both. |
| Monica | `notes-documentation` | Notes with tagging, search, and linking to contacts. Not a formal knowledge base but demonstrates the note-as-knowledge pattern. | Notes linked to contacts can serve as informal knowledge. Consider "pin as article" functionality for promoting notes to knowledge base articles. |
| EspoCRM | `formula-engine` | Not a knowledge base per se, but EspoCRM's formula engine allows defining calculated fields and business rules -- a form of encoded knowledge. | Business rules as encoded knowledge is a different approach. Consider: knowledge base articles can include "decision trees" (if citizen asks X, then answer Y). |

**Key takeaway:** KISS's Elasticsearch-backed knowledge base integrated into the contactmoment flow is the benchmark. Our spec matches this approach. Differentiator: versioned articles linked to zaaktypen (KISS articles are not version-controlled), usefulness analytics for content improvement, public/internal visibility toggle. Adopt: in-context search during contacts (KISS), feedback loop (KISS), category taxonomy (Open Klant).

---

## 24. terugbel-taakbeheer (Pipelinq)
**Tender demand:** 31%
**Competitors with this capability:**

| Competitor | Their spec | Approach | What we can learn |
|------------|-----------|----------|-------------------|
| KISS | `contactverzoek-management` | Contactverzoeken (callback requests) created during contactmoment. Assigned to teams/individuals. Status tracking. Integrated with Open Klant's InterneTaak API. | KISS has basic contactverzoek (callback) support integrated with Open Klant's InterneTaak standard. We must be compatible with this API. |
| Open Klant | `interne-taken` | InterneTaak API: tasks created by KCC for backoffice handling. Fields: gevraagde handeling, toelichting, status, actor. Links to klantcontact. | VNG InterneTaak standard. Our taak model must be compatible. Key fields: gevraagde handeling (what to do), actor (assigned to), status. |
| Open VTB | `taken` | Citizen-facing task management. Tasks created by the backoffice appear in the citizen's portal. Status updates visible to citizens. | Integration with citizen portal: when a terugbelverzoek is completed, the citizen should see the result. Plan for this as a V1 feature. |
| ArkCase | `task-management` | Task management with queues, priorities, deadlines, delegation, escalation. Overdue task notifications. | Escalation rules (if task overdue by X days, escalate to team lead) are important for SLA compliance. Build into our terugbel-taakbeheer. |
| Flowable | `task-service` | Task lifecycle: create, claim, delegate, resolve, complete. Task variables for carrying context. Due date and priority. | Standard task lifecycle. Our taak objects should support the same state transitions. |

**Key takeaway:** Our spec is well-aligned with the VNG InterneTaak standard (Open Klant). Differentiator: SLA tracking with escalation (ArkCase pattern), preferred callback time slots, integration with Nextcloud notification system. Adopt: InterneTaak API compatibility (Open Klant), escalation rules (ArkCase), citizen visibility via Open VTB.

---

## 25. document-creatie-sjablonen (Docudesk)
**Tender demand:** 39%
**Competitors with this capability:**

| Competitor | Their spec | Approach | What we can learn |
|------------|-----------|----------|-------------------|
| Dimpact ZAC | `smart-documents-integration` | SmartDocuments commercial product integration: template management, data merge, PDF generation. Wizard-based document creation. | SmartDocuments is the market leader for government document creation. We replace it with open-source Twig templates + mPDF. Our approach is more flexible and vendor-independent. |
| Valtimo | `document-generation` | Freemarker-based template rendering. Case data merged into templates. Output to ZGW Documenten API. Integrated into BPMN workflows (generate document as a process step). | Workflow-integrated document generation (generate as a BPMN task) is a pattern we should replicate with n8n. "Generate document" should be an n8n node. |
| ArkCase | `correspondence-templates` | Correspondence management with templates. Merge fields from case data. Email and letter templates with approval workflow before sending. | Approval workflow before document sending is important for official correspondence. Consider integrating with our bw-parafering for approved documents. |
| xxllnc Zaken | `document-management` | Document generation from case data. Templates configured per zaaktype. Automatic PDF generation for beschikkingen. | Per-zaaktype template configuration: each zaaktype knows which templates are available for generation. Adopt this linkage. |
| Open Formulieren | `form-engine` | Confirmation PDF generation from form submission data. Template-based with form variable merge. | Confirmation PDF generation for intake acknowledgements. Our document-creatie-sjablonen should support this use case too. |

**Key takeaway:** Our Twig-based approach is architecturally clean and vendor-independent (vs SmartDocuments dependency). Differentiator: ODF output (Dutch government requirement), bulk generation, NL Design System styling integration, template versioning. Adopt: workflow-integrated generation (Valtimo), per-zaaktype template configuration (xxllnc), approval workflow integration. Avoid: SmartDocuments dependency -- our independence is a selling point.

---

## 26. stuf-adapter (OpenConnector)
**Tender demand:** 79%
**Competitors with this capability:**

| Competitor | Their spec | Approach | What we can learn |
|------------|-----------|----------|-------------------|
| Dimpact ZAC | `case-management` | ZAC uses ZGW APIs exclusively. No StUF support. Relies on external adapters for legacy integration. | Even ZAC, a major Dutch government product, doesn't implement StUF. They rely on external translation layers. Confirms that a standalone StUF adapter is the right approach. |
| OpenZaak | `zaak-lifecycle` | OpenZaak is ZGW-native. StUF-ZKN import was available via `cmis-adapter` for migration, now deprecated. | StUF is being phased out but 79% of tenders still require it. The adapter must be robust for the transition period (likely 3-5 more years). |
| xxllnc Zaken | `integration-platform` | xxllnc has a built-in integration platform that supports StUF natively (their legacy platform was StUF-based). StUF is a first-class citizen. | xxllnc's StUF support comes from their legacy heritage -- they didn't build an adapter, they evolved from StUF. We must build an adapter but can match their StUF coverage. |
| Open Formulieren | `registration-backends` | StUF-ZKN registration backend: form submissions can be sent to legacy StUF systems via `creeerZaak_Lk01`. | Open Formulieren's StUF-ZKN registration backend is a reference for the outbound StUF-ZKN direction. Study their XML generation. |

**Key takeaway:** StUF support is critical for legacy interoperability but no modern competitor builds it natively. Our adapter approach (translate at the boundary) is correct. Must: StUF-BG 3.10 (personen) and StUF-ZKN 3.10 (zaken) in both directions. Study: Open Formulieren's StUF-ZKN implementation for XML generation patterns. Key risk: StUF's complexity (namespaces, noValue handling, scope filtering) makes this a high-effort feature.

---

## 27. ibabs-notubiz-connector (OpenConnector)
**Tender demand:** ~20 tenders (12+ iBabs, 8+ NotuBiz)
**Competitors with this capability:**

| Competitor | Their spec | Approach | What we can learn |
|------------|-----------|----------|-------------------|
| Dimpact ZAC | `task-management` | No iBabs/NotuBiz integration. B&W besluitvorming handled via generic task workflow. | ZAC lacks RIS integration entirely. This is a significant gap for gemeenten with iBabs/NotuBiz. |
| xxllnc Zaken | `integration-platform` | xxllnc has an integration platform that can connect to third-party systems, but no specific iBabs/NotuBiz connector documented. | xxllnc could potentially build this via their integration platform but it's not a standard offering. |
| Valtimo | `plugin-system` | Valtimo's plugin system could support RIS integration, but no pre-built iBabs/NotuBiz plugin exists. | Confirms this is a greenfield opportunity. No competitor offers pre-built RIS integration. |

**Key takeaway:** This is a unique capability -- no competitor has pre-built iBabs/NotuBiz integration. Combined with our bw-parafering spec, this creates an end-to-end B&W besluitvorming workflow that doesn't exist elsewhere. Build it well -- it's a major differentiator for gemeente tenders.

---

## 28. dso-omgevingsloket (OpenConnector)
**Tender demand:** 32% (all VTH-related tenders)
**Competitors with this capability:**

| Competitor | Their spec | Approach | What we can learn |
|------------|-----------|----------|-------------------|
| Dimpact ZAC | `productaanvraag-intake` | ZAC receives DSO-verzoeken indirectly: DSO -> Open Formulieren -> Objects API -> ZAC. No direct DSO-LV integration. | ZAC's indirect approach (DSO via Open Formulieren) works but adds latency and a dependency. Our direct DSO-LV integration via STAM koppelvlak is more efficient. |
| xxllnc Zaken | `integration-platform` | xxllnc supports DSO integration via their platform. They handle the Omgevingswet transition for municipalities on their zaaksysteem. | xxllnc has DSO support -- they're a full-featured VTH zaaksysteem. Study their activiteiten-to-zaaktype mapping for coverage. |
| OpenZaak | `zaak-lifecycle` | OpenZaak is the backend for DSO-created zaken. The zaak arrives via the creating application (DSO adapter or form engine). | OpenZaak receives DSO zaken but doesn't implement the DSO adapter itself. Confirms the adapter is a separate component. |
| Open Formulieren | `registration-backends` | Can act as DSO intake channel when configured with ZGW registration backend. Limited to form-based DSO interactions. | Open Formulieren as an intermediary for DSO. Works for citizen-initiated submissions but not for system-to-system DSO-LV integration. |

**Key takeaway:** Direct DSO-LV integration via STAM koppelvlak is rare -- most competitors go through intermediaries. Our direct adapter (receive DSO-verzoeken, map activiteiten to zaaktypen, create zaken, push status updates back) is a strong VTH differentiator. Must: PKIoverheid mTLS authentication, activiteiten mapping table, samenwerking (DSO-SWF). Risk: DSO-LV API complexity and certificate management.

---

# Summary: Coverage Heat Map

| # | Our Spec | App | Demand | Competitors with overlap | Unique advantage |
|---|---------|-----|--------|-------------------------|------------------|
| 1 | rbac-zaaktype | OpenRegister | 86% | 13 competitors | Two-layer model (schema + field level) |
| 2 | archivering-vernietiging | OpenRegister | 73% | 5 competitors | Integrated with register, not separate system |
| 3 | avg-verwerkingsregister | OpenRegister | 67% | 6 competitors (audit only) | **Unique**: dedicated processing register |
| 4 | notificatie-engine | OpenRegister | 71% | 11 competitors | n8n-orchestrated multi-channel |
| 5 | rapportage-bi-export | OpenRegister | 64% | 9 competitors | Built-in + API export dual track |
| 6 | audit-trail-immutable | OpenRegister | 78% | 9 competitors | Cryptographic chaining |
| 7 | geo-metadata-kaart | OpenRegister | 45% | 6 competitors | BAG/kadaster auto-enrichment |
| 8 | workflow-integration | OpenRegister | 82% | 11 competitors | n8n flexibility (non-BPMN) |
| 9 | zoeken-filteren | OpenRegister | 89% | 9 competitors | RBAC-aware + ZGW data_attrs |
| 10 | document-zaakdossier | OpenRegister | 76% | 8 competitors | Nextcloud Files native |
| 11 | zaak-intake-flow | Procest | 61% | 7 competitors | Multi-channel via n8n |
| 12 | vth-module | Procest | 44% | 6 competitors | End-to-end VTH integration |
| 13 | legesberekening | Procest | 32% | 5 competitors (partial) | **Unique**: automated calculation |
| 14 | bw-parafering | Procest | 38% | 7 competitors (generic) | **Unique**: dedicated B&W route |
| 15 | werkvoorraad | Procest | 85% | 7 competitors | Nextcloud Calendar/Tasks integration |
| 16 | mobiel-inspectie | Procest | 27% | 4 competitors (none dedicated) | **Unique**: offline PWA + GPS + photo |
| 17 | zaaktype-configuratie | Procest | 79% | 6 competitors | Two-layer (catalog + handling params) |
| 18 | case-dashboard-view | Procest | 72% | 7 competitors | Nextcloud-native integration |
| 19 | kcc-werkplek | Pipelinq | 100% | 4 competitors | Nextcloud-native, no vendor lock-in |
| 20 | klantbeeld-360 | Pipelinq | 83% | 6 competitors | **Unique**: dedicated 360 view with AVG logging |
| 21 | contactmomenten-rapportage | Pipelinq | 98% | 5 competitors | **Unique**: KISS lacks reporting |
| 22 | omnichannel-registratie | Pipelinq | 54% | 5 competitors | Structured per-channel metadata |
| 23 | kennisbank | Pipelinq | ~74% | 5 competitors | Versioned articles + zaaktype linking |
| 24 | terugbel-taakbeheer | Pipelinq | 31% | 5 competitors | SLA escalation + NC notifications |
| 25 | document-creatie-sjablonen | Docudesk | 39% | 5 competitors | ODF output + NL Design styling |
| 26 | stuf-adapter | OpenConnector | 79% | 4 competitors | **Unique**: standalone bidirectional adapter |
| 27 | ibabs-notubiz-connector | OpenConnector | ~20 tenders | 3 competitors (none have it) | **Unique**: no competitor offers this |
| 28 | dso-omgevingsloket | OpenConnector | 32% | 4 competitors | Direct DSO-LV via STAM |

## Top Differentiators (unique or significantly better)

1. **avg-verwerkingsregister** -- No competitor has a dedicated AVG processing register
2. **legesberekening** -- Automated tariff calculation is unmatched
3. **bw-parafering** -- Dedicated B&W route with iBabs/NotuBiz integration
4. **mobiel-inspectie** -- Offline PWA with GPS + photo is greenfield
5. **ibabs-notubiz-connector** -- No competitor has pre-built RIS integration
6. **contactmomenten-rapportage** -- KISS (main competitor) lacks reporting
7. **klantbeeld-360** -- Dedicated 360 view with mandatory AVG logging
8. **stuf-adapter** -- Standalone bidirectional StUF adapter

## Areas Where Competitors Are Ahead

1. **Workflow engines** -- Flowable/Valtimo have mature BPMN/CMMN; our n8n approach is newer
2. **Dashboard builders** -- Directus/Valtimo have drag-and-drop dashboard builders; we need to build this
3. **Report builders** -- EspoCRM has a full self-service report builder; we should integrate external BI
4. **Real-time updates** -- Dimpact ZAC has WebSocket events; we rely on Nextcloud polling
5. **CMMN case modeling** -- Flowable/CaseFabric have visual CMMN designers; we use configuration-based case types
