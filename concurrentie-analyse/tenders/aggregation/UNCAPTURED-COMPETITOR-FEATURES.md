# Uncaptured Competitor Features

> Analysis of 408 competitor specs across Procest (122), Pipelinq (117), and OpenRegister (169) categories.
> Features NOT covered by our 28 tender-derived specs.
> Generated: 2026-03-15

---

## Category: Procest

### HIGH PRIORITY

### Citizen Self-Service Portal (MijnZaken / PIP)
- **Found in:** xxllnc-zaken (citizen-portal), arkcase (portal-gateway), open-formulieren (submission-lifecycle)
- **Description:** A public-facing portal where citizens can view their case status, exchange documents, send/receive messages, and submit new requests 24/7. Includes authentication (DigiD/eHerkenning), branding per municipality, and bidirectional document exchange.
- **Our coverage:** None
- **Recommendation:** Create spec
- **Priority:** High
- **Rationale:** Every competitor with government case management has a citizen portal. Municipalities increasingly mandate self-service portals for citizens. Without this, Procest cannot compete in tenders that require "MijnZaken" functionality. This is the single largest gap.

### Payment Processing (Leges/iDEAL)
- **Found in:** open-formulieren (payment-processing), xxllnc-zaken (payments), open-vtb (betaaltaak)
- **Description:** Integration with payment gateways (Ogone/Worldline) for case-related fees (leges). Includes payment status tracking, webhook callbacks, price calculation (static or dynamic via DMN), payment-gated registration, and iDEAL/credit card support. Open VTB adds a "betaaltaak" model where the government creates a payment task for the citizen.
- **Our coverage:** Partial (legesberekening spec covers calculation but not payment gateway integration or citizen payment flow)
- **Recommendation:** Merge into existing spec "legesberekening" and extend with payment gateway integration
- **Priority:** High
- **Rationale:** Payment processing is essential for permits and licenses. Three competitors have this. Our legesberekening spec covers the calculation side but not the actual payment flow. This is a tender differentiator.

### Form Engine / Citizen-Facing Forms
- **Found in:** open-formulieren (form-engine, form-builder-admin, form-logic, form-variables, multi-step-wizard, file-uploads, validation-plugins), valtimo (form-system), flowable (form-engine), xxllnc-zaken (process-builder)
- **Description:** A no-code form builder with drag-and-drop interface, conditional logic (JSON Logic), multi-step wizard flows, file uploads, form variables, validation, and form versioning/export. Open Formulieren uses Form.io JSON schema. Forms are the primary citizen-facing interface for case intake.
- **Our coverage:** Partial (zaak intake spec covers intake workflow but not the form builder itself)
- **Recommendation:** Create spec "citizen-form-engine"
- **Priority:** High
- **Rationale:** Four competitors have dedicated form engines. Municipalities expect form builders for configuring citizen-facing intake forms. This is closely tied to the citizen portal feature.

### Prefill from Authoritative Sources (BRP/KvK/Suwinet)
- **Found in:** open-formulieren (prefill-plugins)
- **Description:** Automatic population of form fields with data from authoritative government sources: Haal Centraal BRP (citizen data by BSN), KvK API (company data), StUF-BG (legacy SOAP), Suwinet (social services), eIDAS (EU cross-border), and Yivi/IRMA (self-sovereign identity). Prefill is triggered by authenticated identity and reduces data entry errors.
- **Our coverage:** None
- **Recommendation:** Create spec "authoritative-data-prefill"
- **Priority:** High
- **Rationale:** This is a core Dutch government requirement. Every form that asks for citizen data should prefill from BRP. Municipalities explicitly expect this capability. The plugin architecture of Open Formulieren is a good model.

### Co-signing Workflow (Medeondertekening)
- **Found in:** open-formulieren (cosigning)
- **Description:** A second person must authenticate (DigiD/eHerkenning) and approve a submission before it is registered as a case. Uses email-based out-of-band approval flow with OTP verification. Registration is blocked until co-sign is complete.
- **Our coverage:** Partial (B&W parafering spec covers internal approval but not citizen-facing co-signing)
- **Recommendation:** Merge into existing spec "B&W parafering" or create separate citizen co-signing spec
- **Priority:** High
- **Rationale:** Required for many municipal forms (e.g., both parents must sign for youth applications). Open Formulieren has a mature V2 implementation. This is a common tender requirement.

### Besluiten (Formal Decision Management)
- **Found in:** openzaak (besluiten-api), dimpact-zac (decision-management)
- **Description:** ZGW-compliant formal decision registration with decision types from the catalog, publication dates, response periods (bezwaartermijn), withdrawal support, and linked documents. Decisions are first-class entities in the BRC (Besluiten Registratie Component).
- **Our coverage:** None
- **Recommendation:** Create spec "besluiten-management"
- **Priority:** High
- **Rationale:** Every ZGW-compliant case management system must handle formal decisions (besluiten). This is part of the ZGW standard and expected in tenders. Without it, Procest cannot complete the zaak lifecycle.

### Appointment Scheduling (Afspraken)
- **Found in:** open-formulieren (appointments)
- **Description:** Integration with municipal appointment systems (JCC, Qmatic) for booking appointments as part of form submissions. Includes product/location/timeslot selection, self-service cancellation/modification, and confirmation emails.
- **Our coverage:** None
- **Recommendation:** Create spec "appointment-scheduling"
- **Priority:** High
- **Rationale:** Physical service delivery (balie) requires appointment scheduling. This is standard in Dutch municipalities and often asked in tenders. The plugin architecture allows supporting different appointment backends.

### Agentic AI / AI-Assisted Case Processing
- **Found in:** flowable (agentic-ai), directus (ai-assistant), baserow (ai-features), twenty (ai-features), krayin (ai-lead-extraction)
- **Description:** AI agents embedded in case/data management: orchestrator agents for case routing, knowledge agents (RAG) for Q&A, document AI for classification and extraction, and utility agents for tool execution. Flowable integrates AI directly into the CMMN engine with full audit trail.
- **Our coverage:** None (we have MCP integration but no AI agent framework)
- **Recommendation:** Create spec "ai-assisted-processing"
- **Priority:** High
- **Rationale:** AI is the fastest-growing competitive differentiator. Five competitors are building AI features. Our MCP integration gives us a foundation, but we need a coherent AI strategy for case processing: document classification, data extraction, knowledge base Q&A, and decision support. This is increasingly appearing in tenders as a "nice to have" that will become mandatory.

### MEDIUM PRIORITY

### Mijn Overheid Berichtenbox Integration
- **Found in:** open-vtb (berichten), xxllnc-zaken (communication)
- **Description:** Sending official government messages to the national Mijn Overheid berichtenbox. Messages follow strict format requirements (plain text, single PDF attachment), use bericht type codes for routing, and have read tracking. This is the government-mandated channel for official correspondence.
- **Our coverage:** None
- **Recommendation:** Create spec "mijn-overheid-integration"
- **Priority:** Medium
- **Rationale:** Mijn Overheid is the national citizen portal. Government communication increasingly must go through this channel. Two competitors support it natively.

### Consultation Management (Adviesaanvraag)
- **Found in:** arkcase (consultation-management)
- **Description:** A "mini-case" linked to a parent case for requesting input from another department. Has its own lifecycle, status, participants, documents, and due dates. Enables formal inter-departmental coordination with tracking.
- **Our coverage:** None
- **Recommendation:** Create spec "inter-departmental-consultation"
- **Priority:** Medium
- **Rationale:** Dutch government cases frequently require advies (advice) from other departments. Currently this is handled informally. A structured consultation entity would differentiate Procest.

### Complaint Management (Klachtafhandeling)
- **Found in:** arkcase (complaint-management)
- **Description:** Separate complaint entity with its own lifecycle, escalation to case files, close/approval workflows, disposition tracking, and frequency tracking. Complaints are a distinct intake channel from regular cases.
- **Our coverage:** Partial (zaak intake covers generic intake but not complaints as a first-class entity)
- **Recommendation:** Create spec "complaint-management"
- **Priority:** Medium
- **Rationale:** Klachtafhandeling is a common municipal process type. Having complaints as first-class entities with escalation to cases is a proven pattern.

### Milestone / Progress Tracking
- **Found in:** valtimo (milestone-tracking), casefabric (business-identifiers)
- **Description:** Business-friendly progress indicators mapped to BPMN flow nodes. Milestones abstract technical process state into "how far along is this case" for case workers and managers. Includes milestone sets per case type, visual progress bars, and reached/not-reached status with timestamps.
- **Our coverage:** Partial (case dashboard shows status but no milestone abstraction)
- **Recommendation:** Merge into existing spec "case dashboard" or create separate spec
- **Priority:** Medium
- **Rationale:** Milestones improve case worker UX by showing meaningful progress rather than technical status codes. Two competitors have this. It also benefits citizen portal status displays.

### Event-Driven Architecture / Event Registry
- **Found in:** flowable (event-registry), casefabric (event-sourcing-cqrs), xxllnc-zaken (background-jobs), dimpact-zac (websocket-events)
- **Description:** Standardized event bus for inter-system communication. Flowable's Event Registry provides inbound/outbound channels (JMS, Kafka, RabbitMQ, HTTP) with event correlation to route events to correct process instances. CaseFabric uses full event sourcing with CQRS read projections. xxllnc uses RabbitMQ for all async operations.
- **Our coverage:** Partial (notificaties spec covers notifications but not a general event bus architecture)
- **Recommendation:** Create spec "event-driven-architecture"
- **Priority:** Medium
- **Rationale:** Event-driven architecture enables loose coupling between components and real-time updates. Four competitors have this. Our n8n integration provides some of this, but a formal event bus would improve extensibility.

### Real-Time WebSocket Updates
- **Found in:** dimpact-zac (websocket-events), directus (realtime-websockets), baserow (real-time-collaboration), pocketbase (realtime-subscriptions), nocobase (workflow-engine)
- **Description:** Push-based real-time updates to the UI when data changes. Dimpact ZAC pushes case/task/document change events via WebSocket. Directus supports REST and GraphQL subscriptions. PocketBase uses simpler SSE (Server-Sent Events). Enables collaborative case processing without manual refresh.
- **Our coverage:** None
- **Recommendation:** Create spec "realtime-updates"
- **Priority:** Medium
- **Rationale:** Five competitors have real-time capabilities. For collaborative case management (multiple case workers on the same case), real-time updates prevent conflicts and improve UX. SSE is the simplest path and Nextcloud already has some SSE support.

### Case Definition Import/Export (DTAP Pipeline)
- **Found in:** valtimo (case-import-export), casefabric (case-migration)
- **Description:** Package complete case definitions (schema, process definitions, forms, plugins, permissions, dashboards) into portable ZIP archives for deployment across environments (dev -> test -> acceptance -> production). CaseFabric also supports live migration of running cases when definitions change.
- **Our coverage:** None
- **Recommendation:** Create spec "case-definition-portability"
- **Priority:** Medium
- **Rationale:** Enterprise deployments require DTAP pipelines. Without import/export, case type configuration must be recreated manually in each environment. Two competitors have mature implementations.

### Multi-Tenancy (Shared Platform)
- **Found in:** casefabric (multi-tenancy), bottlecrm (multi-tenancy), xxllnc-zaken (style-configuration), nocobase (plugin-system)
- **Description:** Logical data isolation for multiple municipalities on a single platform deployment. Each tenant has its own users, cases, configurations, and branding. Cross-tenant queries restricted to platform admins. Tenant-scoped database queries via base table class.
- **Our coverage:** None (Nextcloud has separate instances per municipality)
- **Recommendation:** Create spec "multi-tenant-saas"
- **Priority:** Medium
- **Rationale:** SaaS delivery model requires multi-tenancy. Multiple competitors support it. This is increasingly important as municipalities consider shared platforms (Dimpact model). However, Nextcloud's per-instance model is also viable.

### Observability / Monitoring (OpenTelemetry/Prometheus)
- **Found in:** objects-api (observability-metrics), xxllnc-zaken (background-jobs)
- **Description:** Production-grade observability using OpenTelemetry metrics counters, structured logging (structlog), and integration with Prometheus/Grafana/Promtail. Every CRUD operation increments counters. Docker-compose observability stack included.
- **Our coverage:** None (basic Nextcloud logging only)
- **Recommendation:** Create spec "production-observability"
- **Priority:** Medium
- **Rationale:** Enterprise customers and SaaS operation require monitoring. Observability is increasingly a tender requirement for production deployments. Structured metrics enable SLA reporting.

### Mail-to-Case Integration
- **Found in:** dimpact-zac (mail-integration), xxllnc-zaken (communication), espocrm (email-integration)
- **Description:** Send emails from within case context, with configurable templates per zaaktype. Sent emails are converted to PDF and stored as case documents. Template variables resolved from case/task/document data. Includes email threading linked to cases.
- **Our coverage:** Partial (notificaties spec covers outbound notifications but not email-as-document or email threading)
- **Recommendation:** Create spec "case-email-integration"
- **Priority:** Medium
- **Rationale:** Email is still a primary communication channel with citizens. Three competitors store sent emails as case documents. This creates a complete communication audit trail.

### LOW PRIORITY

### WOO/FOIA Compliance (Government Transparency)
- **Found in:** arkcase (foia-compliance)
- **Description:** Complete WOO/FOIA request processing: request intake, queue-based processing, exemption tracking, document redaction, reading room publication, billing/fees, and NIEM export. ArkCase's most mature module with 30+ specific fields.
- **Our coverage:** None
- **Recommendation:** Skip for now, consider as future module
- **Priority:** Low
- **Rationale:** WOO (Wet open overheid) is increasingly important but is a specialized domain. Most municipalities use dedicated WOO software. Could be a future differentiator but is not a common tender requirement for case management.

### Billing & Time Tracking (Kosten/Uren)
- **Found in:** arkcase (billing-timesheets)
- **Description:** Cost tracking and timesheet management linked to cases. Invoice generation, charge roles, cost allocation per case, approval workflows for timesheets (DRAFT -> SUBMITTED -> APPROVED).
- **Our coverage:** None
- **Recommendation:** Skip
- **Priority:** Low
- **Rationale:** Time tracking on cases is useful for internal cost allocation but not a common municipal tender requirement. Better served by dedicated time tracking tools.

### Rule Engine (Phase-Based Business Rules)
- **Found in:** xxllnc-zaken (rule-engine)
- **Description:** Automatically execute business rules when case state changes. Rules configured per phase in case type, fire after every case mutation via decorator pattern. Enables automatic state transitions and side effects.
- **Our coverage:** Partial (workflow/BPMN spec covers process automation; this is more granular)
- **Recommendation:** Merge into existing spec "workflow/BPMN"
- **Priority:** Low
- **Rationale:** Our BPMN/workflow spec already covers process automation. Phase-based rules could be modeled as n8n workflows triggered by case events.

### Plugin/Extension System
- **Found in:** valtimo (plugin-system), ckan (plugin-system), strapi (plugin-system), nocobase (plugin-system), directus (extensions)
- **Description:** Formal extension architecture: annotation-based plugin discovery, pluggable actions linked to BPMN activities, encrypted property storage, marketplace distribution. Valtimo's approach is particularly relevant: plugins expose configurable actions for no-code integration.
- **Our coverage:** None (Nextcloud has its own app system)
- **Recommendation:** Skip (leverage Nextcloud app ecosystem)
- **Priority:** Low
- **Rationale:** Nextcloud already provides an app ecosystem. Creating a separate plugin system would duplicate effort. Better to invest in good Nextcloud app patterns.

### DMN Decision Tables
- **Found in:** open-formulieren (dmn-decision-tables), flowable (dmn-engine, dmn-decision-engine), open-product (dmn-integration)
- **Description:** DMN (Decision Model and Notation) standard for business rules. Define decision tables with input/output columns, hit policies (first, unique, collect), and connect them to forms or processes for automated decision-making (e.g., price calculation, eligibility determination).
- **Our coverage:** Partial (workflow/BPMN spec mentions decision tables)
- **Recommendation:** Merge into existing spec "workflow/BPMN"
- **Priority:** Low
- **Rationale:** DMN is part of the BPMN ecosystem and already partially covered. n8n can implement decision table logic. Not a separate feature to spec.

### Form Analytics / Submission Statistics
- **Found in:** open-formulieren (analytics)
- **Description:** Pluggable analytics integration (Google Analytics, Matomo, SiteImprove, GovMetric) for tracking form usage. Submission statistics model tracks counts over time. CSP header management per analytics tool.
- **Our coverage:** Partial (rapportage/BI spec covers reporting)
- **Recommendation:** Skip
- **Priority:** Low
- **Rationale:** Form analytics is a niche feature. Municipalities typically use separate analytics tools. Our BI/rapportage spec covers the broader reporting need.

---

## Category: Pipelinq

### HIGH PRIORITY

### Activity Timeline / Interaction History
- **Found in:** twenty (activity-tracking), espocrm (reports-dashboards), krayin (activities), bottlecrm (tasks), monica (journal-activity-tracking)
- **Description:** Comprehensive activity feed on every record: timeline activities (audit log), notes, tasks, emails, and calls in chronological order. Every action by a workspace member is recorded with timestamp and actor. The timeline provides full context for customer interactions.
- **Our coverage:** None
- **Recommendation:** Create spec "activity-timeline"
- **Priority:** High
- **Rationale:** Five out of seven CRM competitors have activity timelines. This is the core "know what happened" feature for relationship management. Without it, pipeline management lacks context. Essential for klantbeeld.

### Lead Management / Lead Scoring
- **Found in:** espocrm (lead-management), krayin (leads), bottlecrm (leads), erxes (contacts-crm)
- **Description:** Lead as a first-class entity separate from contacts. Lead status tracking, source attribution, conversion to contact/opportunity, lead scoring, and lead assignment rules. Krayin adds AI-powered lead extraction from uploaded documents.
- **Our coverage:** None
- **Recommendation:** Create spec "lead-management"
- **Priority:** High
- **Rationale:** Four competitors have dedicated lead management. Leads are the entry point for pipeline management. Without leads, Pipelinq starts at the opportunity stage and misses the qualification funnel.

### Email/Calendar Sync
- **Found in:** twenty (email-calendar-sync, email-calendar-integration), espocrm (email-integration), krayin (email)
- **Description:** Two-way sync with email providers (Gmail, Outlook, SMTP/IMAP). Emails automatically linked to CRM records by contact matching. Calendar event sync with auto-contact creation from meeting participants. Domain-based company linking. Visibility controls per account.
- **Our coverage:** None
- **Recommendation:** Create spec "email-calendar-integration"
- **Priority:** High
- **Rationale:** Three competitors sync email/calendar. Email is the primary B2B communication channel. Without email integration, pipeline context is incomplete. Nextcloud already has mail and calendar apps that could be leveraged.

### MEDIUM PRIORITY

### Product Catalog & Quoting
- **Found in:** krayin (products, quotes), bottlecrm (invoices), espocrm (entity-customization)
- **Description:** Product catalog with SKU, pricing, and inventory. Quote generation with line items, discounts, tax, and shipping. Quotes linked to leads/opportunities. PDF generation for proposals. BottleCRM extends to full invoicing with payment tracking and client portal.
- **Our coverage:** None
- **Recommendation:** Create spec "product-catalog-quoting"
- **Priority:** Medium
- **Rationale:** Three competitors have product/quote features. For government service delivery, this maps to "producten en diensten" catalogs. The combination of product catalog + pricing + quoting is a common CRM expectation.

### Web-to-Lead Forms (Embeddable Intake)
- **Found in:** krayin (web-forms), erxes (omnichannel-inbox), nocobase (public-forms)
- **Description:** Configurable HTML forms that can be embedded on external websites. Form submissions create leads/contacts in the CRM. Customizable styling, field selection, and success actions. Some include spam protection and conditional fields.
- **Our coverage:** None
- **Recommendation:** Create spec "public-intake-forms"
- **Priority:** Medium
- **Rationale:** Web forms are the primary digital intake channel. Three competitors have this. For government use, this enables embedding intake forms on municipality websites.

### Data Import/Export (CSV/Excel)
- **Found in:** krayin (data-import), directus (import-export), baserow (search-export)
- **Description:** Batch import of records from CSV/Excel with field mapping, validation, batched processing, error reporting, and progress tracking. Export to CSV/Excel/JSON. Sample file downloads for import templates.
- **Our coverage:** None
- **Recommendation:** Create spec "data-import-export"
- **Priority:** Medium
- **Rationale:** Data migration and bulk operations are essential for onboarding. Three competitors have this. Municipalities need to import existing data when switching systems.

### Automation / Workflow Engine (CRM-level)
- **Found in:** twenty (workflow-automation), espocrm (bpm-workflow-engine), erxes (automation-engine), krayin (automation-workflows)
- **Description:** Visual workflow builders for CRM automation: trigger-action workflows, conditional branching, scheduled actions, and integration with external systems. EspoCRM has a full BPM engine with BPMN-like flows. Twenty plans AI agents in workflows.
- **Our coverage:** Partial (workflow/BPMN spec exists for Procest but not for Pipelinq specifically)
- **Recommendation:** Merge into existing workflow spec or create Pipelinq-specific automation spec
- **Priority:** Medium
- **Rationale:** Four competitors have built-in automation. Our n8n integration already provides this capability. The gap is in exposing automation in the Pipelinq UI, not in the engine itself.

### Ticket / Support System
- **Found in:** erxes (tickets-support)
- **Description:** Issue tracking system with ticket pipelines, custom statuses, priority levels, SLA tracking, and assignment. Separate from sales pipelines but sharing the pipeline UI pattern. Supports ticket types: bug, feature, question, incident.
- **Our coverage:** Partial (terugbel/taakbeheer covers task management)
- **Recommendation:** Skip (can be modeled as a pipeline in Pipelinq)
- **Priority:** Medium
- **Rationale:** Ticket management can be implemented as a pipeline configuration in Pipelinq. No separate spec needed if pipeline views are flexible enough.

### Relationship Mapping (Contact Networks)
- **Found in:** monica (relationships), arkcase (person-organization)
- **Description:** Bidirectional typed relationships between contacts (parent/child, partner, colleague, etc.). Automatic inverse relationship creation. Relationship groups for categorization. Visual relationship graph. This provides the "social network" view of contact connections.
- **Our coverage:** None
- **Recommendation:** Create spec "contact-relationship-mapping"
- **Priority:** Medium
- **Rationale:** Understanding relationships between contacts is valuable for government context (family relationships for social domain cases, company structures for permit applications). Two competitors have this. Maps to "betrokkenen bij zaak" concept.

### Formula / Computed Fields
- **Found in:** espocrm (formula-engine), baserow (formula-system), nocodb (formula-engine)
- **Description:** Server-side formula language for computed fields. Full expression syntax with string, number, date, entity, and cross-record functions. Formulas compiled to SQL for efficient execution. Used for calculated fields, validation rules, and before-save logic.
- **Our coverage:** None
- **Recommendation:** Create spec "computed-fields"
- **Priority:** Medium
- **Rationale:** Three competitors have formula engines. Computed fields reduce manual data entry and ensure consistency. Useful for auto-calculating totals, due dates, and derived fields.

### LOW PRIORITY

### Marketing Campaigns / Mass Email
- **Found in:** krayin (marketing-campaigns), espocrm (campaign-marketing)
- **Description:** Event-triggered email campaigns with templates, batch sending, and spooling. EspoCRM adds audience segmentation, A/B testing, and tracking (open/click).
- **Our coverage:** None
- **Recommendation:** Skip
- **Priority:** Low
- **Rationale:** Marketing campaigns are not relevant for government pipeline management. Better served by dedicated marketing tools or n8n workflows.

### Warehouse / Inventory Management
- **Found in:** krayin (warehouses)
- **Description:** Warehouse locations and product inventory tracking with allocated/in-stock quantities.
- **Our coverage:** None
- **Recommendation:** Skip
- **Priority:** Low
- **Rationale:** Not relevant for government pipeline management. Krayin-specific feature from its e-commerce heritage.

### Reminders / Notification Channels
- **Found in:** monica (reminders-notifications)
- **Description:** Contact-linked reminders for important dates with multi-channel delivery (email, Telegram). Birthday auto-reminders, recurring reminders, notification channel management.
- **Our coverage:** Partial (notificaties spec covers notifications)
- **Recommendation:** Skip (existing notification spec covers this)
- **Priority:** Low
- **Rationale:** Our notification spec already covers alerting. Contact-specific reminders can be modeled as tasks.

### Mood Tracking / Life Events
- **Found in:** monica (mood-tracking, life-events)
- **Description:** Personal CRM features for tracking emotional states and life milestones of contacts. Very personal/consumer-focused.
- **Our coverage:** None
- **Recommendation:** Skip
- **Priority:** Low
- **Rationale:** Not relevant for government or professional use cases. Monica-specific personal CRM features.

---

## Category: OpenRegister

### HIGH PRIORITY

### Content Versioning / Draft Branching
- **Found in:** directus (content-versioning), strapi (content-versioning, review-workflows), objects-api (record-versioning-history), baserow (templates-snapshots)
- **Description:** Git-like content branching: create named draft versions of records, collaborate on changes, and promote (merge) into the main/published version. Only changed fields stored as delta. Combined with review workflows for multi-stage approval before publication. Separate from audit history.
- **Our coverage:** None (we have audit trail for change history but no draft/branching model)
- **Recommendation:** Create spec "content-versioning-drafts"
- **Priority:** High
- **Rationale:** Four competitors have content versioning. For register management, this enables "prepare changes, review, then publish" workflows. Essential for government data quality where changes must be reviewed before going live.

### Built-in Analytics Dashboards
- **Found in:** directus (insights-dashboards), baserow (view-types), nocobase (data-visualization), nocodb (views)
- **Description:** Drag-and-drop dashboard builder with chart types (bar, line, pie, time series), metric panels, and auto-refresh. Dashboards query register data directly. No external BI tool needed for basic analytics.
- **Our coverage:** Partial (rapportage/BI spec covers reporting but assumes external tools)
- **Recommendation:** Create spec "built-in-dashboards"
- **Priority:** High
- **Rationale:** Four competitors have built-in dashboards. For register management, quick visual overview of data is essential. Our rapportage/BI spec assumes external tools (Metabase, etc.) which adds deployment complexity. A built-in lightweight dashboard reduces the barrier.

### MEDIUM PRIORITY

### Application Builder / No-Code Apps
- **Found in:** baserow (application-builder), nocobase (ui-builder)
- **Description:** No-code application builder that creates web applications from register data. Drag-and-drop page builder with 20+ component types, data sources, custom domains, and workflow actions. Enables building citizen-facing apps on top of register data without coding.
- **Our coverage:** None
- **Recommendation:** Create spec "no-code-app-builder"
- **Priority:** Medium
- **Rationale:** Two competitors offer no-code app builders. This is a powerful feature for municipalities that want to create custom applications (portals, dashboards) on top of their register data. However, Nextcloud apps and the existing UI framework may serve this need.

### Data Sync / Harvesting (Federation)
- **Found in:** baserow (data-sync), ckan (harvesting), directus (flows-automation)
- **Description:** Automatic synchronization of data from external sources: other databases, APIs, CKAN instances, or file feeds. CKAN's harvesting framework follows a three-stage pipeline (gather -> fetch -> import) for federated data ecosystems. Baserow supports PostgreSQL, iCal, GitHub, GitLab, Jira, and HubSpot sources.
- **Our coverage:** None
- **Recommendation:** Create spec "data-sync-harvesting"
- **Priority:** Medium
- **Rationale:** Three competitors have data sync. For government registers, harvesting from national sources (e.g., BAG, BRP, KvK) and federating with other municipality registers is a real need. Our OpenConnector app may partially serve this purpose.

### Internationalization / Translation Management
- **Found in:** open-product (i18n-translations), strapi (i18n), directus (translations-i18n), nocobase (i18n-localization)
- **Description:** Multi-language content management with per-field translation tables, language negotiation (Accept-Language header), and fallback chains. Open Product supports NL (required) and EN (optional) with dedicated translation endpoints. Strapi supports unlimited locales.
- **Our coverage:** None
- **Recommendation:** Create spec "register-i18n"
- **Priority:** Medium
- **Rationale:** Four competitors have i18n support. For SDG (Single Digital Gateway) compliance, product/service information must be available in English. Government registers increasingly need multilingual content for EU cross-border services.

### Product Type / Service Catalog Management
- **Found in:** open-product (product-type-management, product-instance-lifecycle, upl-compliance, sdg-doelgroep-compliance, thema-categorization, content-management, pricing-engine)
- **Description:** Comprehensive government product/service catalog: product types with UPL (Uniforme Productnamenlijst) compliance, SDG doelgroep classification, date-range publication, structured content blocks, pricing with DMN rules, and multilingual support. First-class entity model for government products.
- **Our coverage:** None
- **Recommendation:** Create spec "product-service-catalog"
- **Priority:** Medium
- **Rationale:** Open Product is a direct competitor for this space. Government product catalogs are mandated (UPL, SDG). Our softwarecatalog app covers software but not government services/products. This is a natural extension of OpenRegister's data management capability.

### URN/URL Mapping System
- **Found in:** open-product (urn-mapping), open-vtb (urn-addressing)
- **Description:** Bidirectional URN-URL mapping for cross-system resource identification. Pattern: organisatie:systeem:component:resource:uuid. Auto-resolution between URN and URL via configurable mapping tables. Enables system-independent addressing of government resources.
- **Our coverage:** None
- **Recommendation:** Create spec "urn-resource-addressing"
- **Priority:** Medium
- **Rationale:** Two competitors use URN addressing. This is part of the Dutch government standards ecosystem (VNG). URN-based addressing enables location-independent resource references, which is important for multi-vendor environments.

### GraphQL API
- **Found in:** directus (graphql-api), strapi (graphql-api), nocodb (api-rest)
- **Description:** Full GraphQL API alongside REST. Schema auto-generated from data model. Supports queries, mutations, and subscriptions. Reduces over-fetching and enables efficient nested data retrieval.
- **Our coverage:** None (OpenRegister has REST API only)
- **Recommendation:** Create spec "graphql-api"
- **Priority:** Medium
- **Rationale:** Three competitors offer GraphQL. Modern frontend applications prefer GraphQL for its efficiency. However, REST is sufficient for most government use cases. Medium priority as a "nice to have" for developer experience.

### Row-Level Security / Field-Level Authorization
- **Found in:** directus (row-level-security), objects-api (field-level-authorization)
- **Description:** Dynamic access rules evaluated per record based on the current user's role and the record's field values. Directus uses JSON filter rules that can reference $CURRENT_USER and $CURRENT_ROLE. Objects API adds field-level filtering: different tokens can see different fields of the same object.
- **Our coverage:** Partial (RBAC/zaaktype spec covers role-based access but not row/field-level)
- **Recommendation:** Merge into existing spec "RBAC/zaaktype" as an enhancement
- **Priority:** Medium
- **Rationale:** Two competitors have granular security beyond role-based access. For sensitive government data (medical, social, legal), field-level and row-level security is important. Our RBAC spec should be extended.

### Sharing / Public Links
- **Found in:** directus (sharing), nocodb (sharing), pocketbase (auto-generated-api)
- **Description:** Token-based share links for specific records with optional password protection, time limits, usage limits, and role-scoped permissions. Enables external collaboration without user accounts.
- **Our coverage:** None
- **Recommendation:** Skip (Nextcloud already has share links for files)
- **Priority:** Medium
- **Rationale:** Nextcloud provides file sharing. For structured data sharing, this could be useful but is not a common government requirement. Lower priority.

### LOW PRIORITY

### Image Transformations (On-the-Fly)
- **Found in:** directus (image-transformations)
- **Description:** Dynamic image resizing, cropping, format conversion via URL parameters. Focal point support, preset transformations, and caching.
- **Our coverage:** None
- **Recommendation:** Skip
- **Priority:** Low
- **Rationale:** Not relevant for government register management. Nextcloud has basic image handling.

### Backup & Restore (Built-in)
- **Found in:** pocketbase (backup-restore)
- **Description:** One-click backup/restore from admin UI with S3 support and cron scheduling.
- **Our coverage:** None (relies on Nextcloud/database-level backups)
- **Recommendation:** Skip (use standard database backup tools)
- **Priority:** Low
- **Rationale:** Enterprise deployments use standard database backup solutions. Built-in backup is nice for small deployments but not a competitive differentiator.

### Calendar/Gantt/Kanban Data Views
- **Found in:** nocobase (calendar-gantt-kanban), baserow (view-types), nocodb (views)
- **Description:** Specialized data visualization: calendar view for date fields, Gantt chart for project timelines, and kanban board for status-based workflows. Each is a different lens on the same underlying data.
- **Our coverage:** None
- **Recommendation:** Skip for OpenRegister (more relevant for Pipelinq)
- **Priority:** Low
- **Rationale:** Calendar and Gantt views are more relevant for project/pipeline management than register management. Kanban is already part of Pipelinq. These could be nice-to-have for register data but are not core functionality.

### Review Workflows (Content Approval)
- **Found in:** strapi (review-workflows)
- **Description:** Multi-stage content approval: define review stages, assign reviewers, enforce approval before publication. Each content type assigned to a workflow.
- **Our coverage:** Partial (B&W parafering covers approval workflows)
- **Recommendation:** Skip (covered by existing parafering spec + content versioning)
- **Priority:** Low
- **Rationale:** Our B&W parafering spec already covers approval workflows. Combined with the proposed content versioning spec, this is sufficiently covered.

### OpenAPI Generation (Auto-Documentation)
- **Found in:** strapi (openapi-generation), objects-api (api-compliancy)
- **Description:** Automatic OpenAPI/Swagger specification generation from the data model. Objects API adds API compliancy checking against the VNG standard spec.
- **Our coverage:** None
- **Recommendation:** Skip (OpenRegister auto-generates API per schema already)
- **Priority:** Low
- **Rationale:** OpenRegister already generates API endpoints per schema. Formal OpenAPI spec generation would be nice but is not a tender differentiator.

---

## Summary: Recommended New Specs

### HIGH PRIORITY (create immediately)
| # | Spec Name | Category | Competitors |
|---|-----------|----------|-------------|
| 1 | Citizen Self-Service Portal | Procest | 3 |
| 2 | Payment Gateway Integration | Procest | 3 |
| 3 | Citizen Form Engine | Procest | 4 |
| 4 | Authoritative Data Prefill | Procest | 1 (critical standard) |
| 5 | Co-signing Workflow | Procest | 1 (common requirement) |
| 6 | Besluiten Management | Procest | 2 |
| 7 | Appointment Scheduling | Procest | 1 (common requirement) |
| 8 | AI-Assisted Processing | Cross-cutting | 5 |
| 9 | Activity Timeline | Pipelinq | 5 |
| 10 | Lead Management | Pipelinq | 4 |
| 11 | Email/Calendar Integration | Pipelinq | 3 |
| 12 | Content Versioning/Drafts | OpenRegister | 4 |
| 13 | Built-in Dashboards | OpenRegister | 4 |

### MEDIUM PRIORITY (create in next phase)
| # | Spec Name | Category | Competitors |
|---|-----------|----------|-------------|
| 14 | Mijn Overheid Integration | Procest | 2 |
| 15 | Inter-departmental Consultation | Procest | 1 |
| 16 | Complaint Management | Procest | 1 |
| 17 | Milestone/Progress Tracking | Procest | 2 |
| 18 | Event-Driven Architecture | Procest | 4 |
| 19 | Real-Time Updates | Cross-cutting | 5 |
| 20 | Case Definition Portability | Procest | 2 |
| 21 | Multi-Tenant SaaS | Procest | 4 |
| 22 | Production Observability | Cross-cutting | 2 |
| 23 | Case Email Integration | Procest | 3 |
| 24 | Product Catalog & Quoting | Pipelinq | 3 |
| 25 | Public Intake Forms | Pipelinq | 3 |
| 26 | Data Import/Export | Cross-cutting | 3 |
| 27 | Contact Relationship Mapping | Pipelinq | 2 |
| 28 | Computed Fields | OpenRegister | 3 |
| 29 | No-Code App Builder | OpenRegister | 2 |
| 30 | Data Sync/Harvesting | OpenRegister | 3 |
| 31 | Register i18n | OpenRegister | 4 |
| 32 | Product/Service Catalog | OpenRegister | 1 (mandated standard) |
| 33 | URN Resource Addressing | OpenRegister | 2 |
| 34 | GraphQL API | OpenRegister | 3 |
| 35 | Row/Field-Level Security | OpenRegister | 2 |

### Total: 13 high-priority + 22 medium-priority = 35 new specs recommended
### Skipped: ~15 features deemed not relevant or already covered
