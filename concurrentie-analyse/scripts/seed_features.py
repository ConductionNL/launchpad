#!/usr/bin/env python3
"""Seed category_features with standard features per software type,
and backfill feature_sources from existing tender requirements and competitor data."""

import sqlite3
import os
import re

DB_PATH = os.path.join(os.path.dirname(os.path.dirname(__file__)), "intelligence.db")

# Standard features per software category
# Format: (category_slug, feature_slug, name_nl, name_en, description, priority, spec_layer)
STANDARD_FEATURES = [
    # === ZAAKSYSTEEM (Case Management) ===
    ("zaaksysteem", "case-lifecycle", "Zaaklevenscyclus", "Case lifecycle management", "Create, assign, treat, close, archive cases", "core", "procest"),
    ("zaaksysteem", "case-types", "Zaaktypeconfiguratie", "Case type configuration", "Define and configure case types with statuses, checklists, deadlines", "core", "procest"),
    ("zaaksysteem", "zgw-api", "ZGW API's", "ZGW APIs", "Compliance with Dutch ZGW API standards (Zaken, Documenten, Catalogi, Besluiten)", "core", "procest"),
    ("zaaksysteem", "document-linking", "Documentkoppeling", "Document linking", "Link documents to cases with versioning", "core", "docudesk"),
    ("zaaksysteem", "workflow-automation", "Workflowautomatisering", "Workflow automation", "BPMN/CMMN process automation for case handling", "core", "procest"),
    ("zaaksysteem", "task-management", "Taakbeheer", "Task management", "Assign tasks, deadlines, escalations, work queues", "core", "procest"),
    ("zaaksysteem", "search-filter", "Zoeken en filteren", "Search and filter", "Full-text search, faceted filtering across cases and documents", "core", "openregister"),
    ("zaaksysteem", "rbac", "Rollen en autorisatie", "Roles and authorization", "Role-based access control per case type, department, user", "core", "openregister"),
    ("zaaksysteem", "audit-trail", "Audittrail", "Audit trail", "Complete mutation history, who changed what when", "core", "openregister"),
    ("zaaksysteem", "archiving", "Archivering en vernietiging", "Archiving and destruction", "Retention policies, selectielijsten, Archiefwet compliance", "core", "openregister"),
    ("zaaksysteem", "forms-intake", "Formulieren en intake", "Forms and intake", "Digital forms, multi-step wizards, e-formulieren integration", "standard", "procest"),
    ("zaaksysteem", "reporting", "Rapportage en dashboards", "Reporting and dashboards", "Management reports, KPI dashboards, statistics", "standard", "mydash"),
    ("zaaksysteem", "notifications", "Notificaties", "Notifications", "Email, in-app, push notifications for case events", "standard", "nextcloud"),
    ("zaaksysteem", "geo-location", "Geografische locatie", "Geographic location", "Link cases to addresses/coordinates, map view", "standard", "openregister"),
    ("zaaksysteem", "brp-integration", "BRP-koppeling", "BRP integration", "Citizen data from Basisregistratie Personen", "standard", "openconnector"),
    ("zaaksysteem", "kvk-integration", "KVK-koppeling", "KVK integration", "Business data from Kamer van Koophandel", "standard", "openconnector"),
    ("zaaksysteem", "digid-auth", "DigiD authenticatie", "DigiD authentication", "Citizen authentication via DigiD/eHerkenning", "standard", "nextcloud"),
    ("zaaksysteem", "document-generation", "Documentgeneratie", "Document generation", "Template-based document creation with merge fields", "standard", "docudesk"),
    ("zaaksysteem", "sub-cases", "Deel- en vervolgzaken", "Sub-cases and follow-up cases", "Create related, child, and follow-up cases", "standard", "procest"),
    ("zaaksysteem", "mobile-access", "Mobiele toegang", "Mobile access", "Mobile-friendly interface or native app", "advanced", "nextcloud"),
    ("zaaksysteem", "common-ground", "Common Ground architectuur", "Common Ground architecture", "Microservices, API-first, NLX/Haven compliance", "advanced", "openconnector"),

    # === VTH ===
    ("vth", "permit-management", "Vergunningenbeheer", "Permit management", "Application intake, assessment, granting permits", "core", "procest"),
    ("vth", "inspection-management", "Toezichtbeheer", "Inspection management", "Plan, execute, report inspections", "core", "procest"),
    ("vth", "enforcement", "Handhaving", "Enforcement", "Violations, sanctions, enforcement actions", "core", "procest"),
    ("vth", "dso-integration", "DSO-koppeling", "DSO integration", "Digitaal Stelsel Omgevingswet connection", "core", "openconnector"),
    ("vth", "mobile-inspection", "Mobiel toezicht", "Mobile inspection", "Tablet/phone inspection forms, offline capability", "standard", "procest"),
    ("vth", "risk-based-supervision", "Risicogericht toezicht", "Risk-based supervision", "Risk profiles, prioritized inspection planning", "standard", "procest"),
    ("vth", "wkb-support", "Wkb-ondersteuning", "Wkb support", "Wet kwaliteitsborging voor het bouwen", "advanced", "procest"),

    # === CRM / Klantinteractie ===
    ("crm", "contact-management", "Contactbeheer", "Contact management", "Manage citizens, businesses, organizations", "core", "pipelinq"),
    ("crm", "interaction-tracking", "Interactieregistratie", "Interaction tracking", "Log calls, emails, visits, chats per contact", "core", "pipelinq"),
    ("crm", "pipeline-management", "Pipelinebeheer", "Pipeline management", "Kanban/funnel view for leads and opportunities", "core", "pipelinq"),
    ("crm", "omnichannel", "Omnichannelcommunicatie", "Omnichannel communication", "Unified inbox: phone, email, web, chat, social", "standard", "pipelinq"),
    ("crm", "klantinteracties-api", "Klantinteracties API", "Customer interactions API", "VNG Klantinteracties/Verzoeken API compliance", "standard", "pipelinq"),
    ("crm", "knowledge-base", "Kennisbank", "Knowledge base", "FAQ, articles, searchable knowledge management", "standard", "pipelinq"),
    ("crm", "routing", "Routering", "Routing", "Skills-based routing of customer requests", "standard", "pipelinq"),
    ("crm", "sla-tracking", "SLA-bewaking", "SLA tracking", "Service level monitoring, response time tracking", "standard", "pipelinq"),
    ("crm", "customer-portal", "Klantportaal", "Customer portal", "Self-service portal for citizens/businesses", "advanced", "pipelinq"),
    ("crm", "sentiment-analysis", "Sentimentanalyse", "Sentiment analysis", "AI-based sentiment detection in communications", "advanced", "pipelinq"),

    # === DMS (Document Management) ===
    ("dms", "document-storage", "Documentopslag", "Document storage", "Upload, store, organize documents in folders/categories", "core", "docudesk"),
    ("dms", "version-control", "Versiebeheer", "Version control", "Automatic versioning, check-in/check-out", "core", "docudesk"),
    ("dms", "metadata-management", "Metadatabeheer", "Metadata management", "Custom metadata fields, auto-classification", "core", "docudesk"),
    ("dms", "full-text-search", "Volledige tekstzoekfunctie", "Full-text search", "Search within document content, OCR support", "core", "openregister"),
    ("dms", "digital-signing", "Digitaal ondertekenen", "Digital signing", "Electronic signatures, PKIoverheid", "standard", "docudesk"),
    ("dms", "document-templates", "Documentsjablonen", "Document templates", "Template-based document generation", "standard", "docudesk"),
    ("dms", "retention-policies", "Bewaarbeleid", "Retention policies", "Automated retention and destruction schedules", "standard", "openregister"),
    ("dms", "collaborative-editing", "Gelijktijdig bewerken", "Collaborative editing", "Real-time co-editing (RTCE)", "advanced", "nextcloud"),
    ("dms", "woo-publication", "WOO-publicatie", "WOO publication", "Wet Open Overheid document publication", "advanced", "docudesk"),

    # === ERP (gap) ===
    ("erp", "financial-accounting", "Financiële administratie", "Financial accounting", "General ledger, accounts payable/receivable", "core", None),
    ("erp", "budgeting", "Budgetbeheer", "Budgeting", "Budget planning, tracking, forecasting", "core", None),
    ("erp", "procurement", "Inkoopbeheer", "Procurement", "Purchase orders, supplier management, approval workflows", "core", None),
    ("erp", "inventory", "Voorraadbeheer", "Inventory management", "Stock tracking, warehousing, logistics", "standard", None),
    ("erp", "hr-payroll", "HR en salarisadministratie", "HR and payroll", "Employee management, payroll processing", "standard", None),
    ("erp", "project-accounting", "Projectadministratie", "Project accounting", "Project budgets, time tracking, billing", "standard", None),
    ("erp", "reporting-bi", "Rapportage en BI", "Reporting and BI", "Financial reports, dashboards, analytics", "standard", None),
    ("erp", "multi-entity", "Multi-entiteitbeheer", "Multi-entity management", "Support for multiple legal entities/departments", "advanced", None),

    # === BOEKHOUDING (gap) ===
    ("boekhouding", "general-ledger", "Grootboek", "General ledger", "Chart of accounts, journal entries, trial balance", "core", None),
    ("boekhouding", "accounts-payable", "Crediteuren", "Accounts payable", "Invoice processing, payment scheduling, vendor management", "core", None),
    ("boekhouding", "accounts-receivable", "Debiteuren", "Accounts receivable", "Invoice generation, payment tracking, reminders", "core", None),
    ("boekhouding", "bank-reconciliation", "Bankreconciliatie", "Bank reconciliation", "Automatic bank statement matching", "core", None),
    ("boekhouding", "vat-reporting", "BTW-aangifte", "VAT reporting", "Dutch BTW calculation, SBR/XBRL filing", "standard", None),
    ("boekhouding", "annual-accounts", "Jaarrekening", "Annual accounts", "Balance sheet, P&L, annual report generation", "standard", None),
    ("boekhouding", "e-invoicing", "E-facturatie", "E-invoicing", "Peppol/UBL electronic invoice sending/receiving", "standard", None),
    ("boekhouding", "multi-currency", "Multivaluta", "Multi-currency", "Support for multiple currencies", "advanced", None),

    # === OBJECTREGISTRATIE ===
    ("objectregistratie", "schema-management", "Schemabeheer", "Schema management", "Define and manage data schemas/object types", "core", "openregister"),
    ("objectregistratie", "crud-api", "CRUD API", "CRUD API", "RESTful create, read, update, delete operations", "core", "openregister"),
    ("objectregistratie", "validation", "Datavalidatie", "Data validation", "JSON Schema validation, required fields, constraints", "core", "openregister"),
    ("objectregistratie", "search-faceting", "Zoeken en facetteren", "Search and faceting", "Full-text search with faceted filtering", "standard", "openregister"),
    ("objectregistratie", "audit-history", "Mutatiehistorie", "Audit history", "Version history per object, who changed what", "standard", "openregister"),
    ("objectregistratie", "access-control", "Toegangsbeheer", "Access control", "Object-level and field-level permissions", "standard", "openregister"),
    ("objectregistratie", "import-export", "Import/export", "Import/export", "CSV, JSON, XML bulk import and export", "standard", "openregister"),
    ("objectregistratie", "webhooks", "Webhooks", "Webhooks", "Event-driven notifications on data changes", "advanced", "openregister"),

    # === PROJECTMANAGEMENT (gap) ===
    ("projectmanagement", "project-planning", "Projectplanning", "Project planning", "Gantt charts, milestones, dependencies", "core", None),
    ("projectmanagement", "task-tracking", "Taakbeheer", "Task tracking", "Create, assign, track tasks with deadlines", "core", None),
    ("projectmanagement", "resource-management", "Resourcebeheer", "Resource management", "Team allocation, capacity planning", "standard", None),
    ("projectmanagement", "time-tracking", "Tijdregistratie", "Time tracking", "Log hours per project/task", "standard", None),
    ("projectmanagement", "collaboration", "Samenwerking", "Collaboration", "Comments, file sharing, real-time updates", "standard", None),
    ("projectmanagement", "portfolio-view", "Portfolioweergave", "Portfolio view", "Overview across multiple projects", "advanced", None),

    # === FORMULIEREN ===
    ("formulieren", "form-builder", "Formulierenbouwer", "Form builder", "Drag-and-drop form creation without coding", "core", "procest"),
    ("formulieren", "conditional-logic", "Conditionele logica", "Conditional logic", "Show/hide fields based on previous answers", "core", "procest"),
    ("formulieren", "multi-step", "Meerstappenformulier", "Multi-step forms", "Wizard-style forms with progress indicator", "standard", "procest"),
    ("formulieren", "file-upload", "Bestandsupload", "File upload", "Upload attachments with forms", "standard", "procest"),
    ("formulieren", "prefill", "Voorinvullen", "Prefill", "Auto-fill from BRP/KVK/DigiD data", "standard", "openconnector"),
    ("formulieren", "validation", "Validatie", "Validation", "Real-time field validation, format checks", "standard", "procest"),
    ("formulieren", "payment-integration", "Betaalintegratie", "Payment integration", "iDEAL/Mollie payment after submission", "advanced", "openconnector"),
    ("formulieren", "accessibility", "Toegankelijkheid", "Accessibility", "WCAG 2.1 AA compliant forms", "core", "nldesign"),
    ("formulieren", "submission-management", "Inzendingenbeheer", "Submission management", "View, filter, export form submissions", "standard", "procest"),

    # === CATALOGUS ===
    ("catalogus", "publication-management", "Publicatiebeheer", "Publication management", "Create and manage catalogue publications", "core", "opencatalogi"),
    ("catalogus", "federated-search", "Federatief zoeken", "Federated search", "Search across federated catalogue instances", "core", "opencatalogi"),
    ("catalogus", "dcat-compliance", "DCAT-compliance", "DCAT compliance", "DCAT-AP NL metadata standard", "core", "opencatalogi"),
    ("catalogus", "metadata-management", "Metadatabeheer", "Metadata management", "Dublin Core, OWMS, TOOI metadata", "standard", "opencatalogi"),
    ("catalogus", "open-data-portal", "Open data portaal", "Open data portal", "Public-facing data catalogue", "standard", "opencatalogi"),
    ("catalogus", "harvest-sync", "Harvesting en sync", "Harvesting and sync", "Harvest from external catalogues", "advanced", "opencatalogi"),

    # === DASHBOARD ===
    ("dashboard", "widget-management", "Widgetbeheer", "Widget management", "Add, remove, configure dashboard widgets", "core", "mydash"),
    ("dashboard", "kpi-visualization", "KPI-visualisatie", "KPI visualization", "Charts, counters, gauges, status indicators", "core", "mydash"),
    ("dashboard", "layout-customization", "Layoutaanpassing", "Layout customization", "Drag-and-drop grid layout", "standard", "mydash"),
    ("dashboard", "role-based-views", "Rolgebaseerde weergaven", "Role-based views", "Different dashboards per role/team", "standard", "mydash"),
    ("dashboard", "data-sources", "Databronnen", "Data sources", "Connect to multiple data sources/APIs", "standard", "mydash"),
    ("dashboard", "export-sharing", "Export en delen", "Export and sharing", "PDF export, scheduled email reports", "advanced", "mydash"),

    # === SOFTWARECATALOGUS ===
    ("softwarecatalogus", "software-registration", "Softwareregistratie", "Software registration", "Register applications with metadata", "core", "softwarecatalog"),
    ("softwarecatalogus", "gemma-mapping", "GEMMA-mapping", "GEMMA mapping", "Map software to GEMMA architecture components", "core", "softwarecatalog"),
    ("softwarecatalogus", "connection-mapping", "Koppelingenoverzicht", "Connection mapping", "Visualize system dependencies", "standard", "softwarecatalog"),
    ("softwarecatalogus", "lifecycle-tracking", "Levenscyclusbeheer", "Lifecycle tracking", "Track software status: active, deprecated, planned", "standard", "softwarecatalog"),
    ("softwarecatalogus", "compliance-check", "Compliancecheck", "Compliance check", "Check against standards (Common Ground, NL Design)", "advanced", "softwarecatalog"),

    # === INTEGRATIE ===
    ("integratie", "api-gateway", "API-gateway", "API gateway", "Reverse proxy, rate limiting, API key management", "core", "openconnector"),
    ("integratie", "data-sync", "Datasynchronisatie", "Data synchronization", "Scheduled sync between systems", "core", "openconnector"),
    ("integratie", "protocol-translation", "Protocoltranslatie", "Protocol translation", "StUF-to-REST, SOAP-to-JSON mapping", "core", "openconnector"),
    ("integratie", "event-bus", "Eventbus", "Event bus", "CloudEvents, webhooks, pub/sub", "standard", "openconnector"),
    ("integratie", "auth-relay", "Authenticatiedoorgifte", "Authentication relay", "OAuth, API keys, certificate auth to external APIs", "standard", "openconnector"),
    ("integratie", "monitoring", "Monitoring", "Monitoring", "Integration health checks, error logging", "standard", "openconnector"),

    # === ONTWERP ===
    ("ontwerp", "design-tokens", "Ontwerptokens", "Design tokens", "CSS custom properties for theming", "core", "nldesign"),
    ("ontwerp", "token-sets", "Tokensets", "Token sets", "Per-organization theme sets (VNG, Den Haag, etc.)", "core", "nldesign"),
    ("ontwerp", "wcag-compliance", "WCAG-compliance", "WCAG compliance", "WCAG 2.1 AA accessibility", "core", "nldesign"),
    ("ontwerp", "responsive-design", "Responsive ontwerp", "Responsive design", "Mobile-friendly, tablet support", "standard", "nldesign"),
    ("ontwerp", "component-library", "Componentenbibliotheek", "Component library", "Reusable NL Design System components", "standard", "nldesign"),

    # === HRM (gap) ===
    ("hrm", "employee-management", "Personeelsbeheer", "Employee management", "Employee records, contracts, departments", "core", None),
    ("hrm", "leave-management", "Verlofbeheer", "Leave management", "Vacation requests, sick leave, approval workflows", "core", None),
    ("hrm", "payroll", "Salarisadministratie", "Payroll", "Salary calculation, tax withholding, pay slips", "core", None),
    ("hrm", "time-registration", "Tijdregistratie", "Time registration", "Clock in/out, timesheet management", "standard", None),
    ("hrm", "performance-reviews", "Beoordelingen", "Performance reviews", "Annual reviews, goals, feedback", "standard", None),
    ("hrm", "recruitment", "Werving", "Recruitment", "Job postings, applicant tracking, onboarding", "advanced", None),
    ("hrm", "training", "Opleidingen", "Training management", "Course registration, certifications tracking", "advanced", None),

    # === GIS (gap) ===
    ("gis", "map-viewer", "Kaartviewer", "Map viewer", "Interactive map display with layers", "core", None),
    ("gis", "spatial-data", "Ruimtelijke data", "Spatial data management", "Store and query geographic objects", "core", None),
    ("gis", "wms-wfs", "WMS/WFS-diensten", "WMS/WFS services", "OGC web map and feature services", "core", None),
    ("gis", "geocoding", "Geocodering", "Geocoding", "Address to coordinate conversion (BAG)", "standard", None),
    ("gis", "spatial-analysis", "Ruimtelijke analyse", "Spatial analysis", "Buffer, overlay, proximity analysis", "standard", None),
    ("gis", "pdok-integration", "PDOK-integratie", "PDOK integration", "Dutch public geo-data services", "standard", None),

    # === E-FACTURATIE (gap) ===
    ("e-facturatie", "peppol-send", "Peppol verzenden", "Peppol send", "Send e-invoices via Peppol network", "core", None),
    ("e-facturatie", "peppol-receive", "Peppol ontvangen", "Peppol receive", "Receive e-invoices via Peppol network", "core", None),
    ("e-facturatie", "ubl-support", "UBL-ondersteuning", "UBL support", "UBL 2.1 invoice format", "core", None),
    ("e-facturatie", "invoice-validation", "Factuurvalidatie", "Invoice validation", "Validate against EU/NL e-invoicing standards", "standard", None),
    ("e-facturatie", "sbr-filing", "SBR-rapportage", "SBR filing", "Standard Business Reporting to Dutch tax authority", "standard", None),

    # === IAM (gap) ===
    ("iam", "sso", "Single sign-on", "Single sign-on", "SAML, OIDC single sign-on", "core", None),
    ("iam", "mfa", "Multi-factor authenticatie", "Multi-factor authentication", "TOTP, SMS, hardware key 2FA", "core", None),
    ("iam", "digid-integration", "DigiD-integratie", "DigiD integration", "Dutch citizen authentication", "core", None),
    ("iam", "eherkenning", "eHerkenning", "eHerkenning", "Dutch business authentication", "core", None),
    ("iam", "user-provisioning", "Gebruikersprovisioning", "User provisioning", "SCIM, automatic account creation/deactivation", "standard", None),
    ("iam", "directory-sync", "Directory-synchronisatie", "Directory sync", "LDAP/AD synchronization", "standard", None),

    # === ARCHIVERING (gap) ===
    ("archivering", "retention-management", "Bewaartermijnbeheer", "Retention management", "Selectielijsten, retention schedules", "core", None),
    ("archivering", "destruction-workflow", "Vernietigingsworkflow", "Destruction workflow", "Approval-based destruction with audit trail", "core", None),
    ("archivering", "edepot-transfer", "e-Depot overdracht", "e-Depot transfer", "Transfer to regional archive (TMLO/MDTO format)", "core", None),
    ("archivering", "durable-formats", "Duurzame formaten", "Durable formats", "Convert to archival formats (PDF/A, ODF)", "standard", None),
    ("archivering", "metadata-tmlo", "TMLO-metadata", "TMLO metadata", "Toepassingsprofiel Metadatering Lokale Overheden", "standard", None),
    ("archivering", "search-archived", "Zoeken in archief", "Search archived records", "Full-text search across archived cases/documents", "standard", None),

    # === WEBSITE-CMS (gap) ===
    ("website-cms", "page-management", "Paginabeheer", "Page management", "Create, edit, publish web pages", "core", None),
    ("website-cms", "content-editor", "Contenteditor", "Content editor", "WYSIWYG or block-based editing", "core", None),
    ("website-cms", "media-management", "Mediabeheer", "Media management", "Upload and manage images, videos, files", "core", None),
    ("website-cms", "menu-navigation", "Menu en navigatie", "Menu and navigation", "Configurable site menus and navigation", "standard", None),
    ("website-cms", "multilingual", "Meertalig", "Multilingual", "Dutch + English content management", "standard", None),
    ("website-cms", "search", "Zoekfunctie", "Search", "Site-wide search with suggestions", "standard", None),
    ("website-cms", "forms-contact", "Formulieren en contact", "Forms and contact", "Contact forms, feedback forms", "standard", None),
    ("website-cms", "accessibility-wcag", "Toegankelijkheid", "Accessibility", "WCAG 2.1 AA, Rijkshuisstijl compliance", "core", None),

    # === PARTICIPATIE (gap) ===
    ("participatie", "consultation", "Raadpleging", "Consultation", "Create consultations with questions/polls", "core", None),
    ("participatie", "voting", "Stemmen", "Voting", "Online voting on proposals or ideas", "core", None),
    ("participatie", "idea-collection", "Ideeënverzameling", "Idea collection", "Citizens submit and discuss ideas", "standard", None),
    ("participatie", "budget-participation", "Burgerbegroting", "Participatory budgeting", "Citizens allocate budget to projects", "advanced", None),
    ("participatie", "reporting-results", "Resultatenrapportage", "Results reporting", "Dashboard showing participation outcomes", "standard", None),

    # === MELDINGEN (gap) ===
    ("meldingen", "incident-intake", "Meldingen-intake", "Incident intake", "Report issues via web/app/phone", "core", None),
    ("meldingen", "geo-tagging", "Locatiebepaling", "Geo-tagging", "Pin incident location on map", "core", None),
    ("meldingen", "photo-upload", "Foto-upload", "Photo upload", "Attach photos to incident reports", "standard", None),
    ("meldingen", "status-tracking", "Statusvolging", "Status tracking", "Reporter can track incident progress", "standard", None),
    ("meldingen", "routing-assignment", "Routering en toewijzing", "Routing and assignment", "Auto-assign to department/team by category", "standard", None),
    ("meldingen", "category-management", "Categoriebeheer", "Category management", "Configurable incident categories", "standard", None),

    # === SUBSIDIES (gap) ===
    ("subsidies", "subsidy-schemes", "Subsidieregelingen", "Subsidy schemes", "Define and publish subsidy programs", "core", None),
    ("subsidies", "application-intake", "Aanvraag-intake", "Application intake", "Online subsidy application forms", "core", None),
    ("subsidies", "assessment-workflow", "Beoordelingsworkflow", "Assessment workflow", "Review, score, approve/reject applications", "core", None),
    ("subsidies", "budget-tracking", "Budgetbewaking", "Budget tracking", "Track committed vs available subsidy budget", "standard", None),
    ("subsidies", "accountability", "Verantwoording", "Accountability", "Grantee reporting and accountability", "standard", None),
    ("subsidies", "payment-processing", "Betalingsverwerking", "Payment processing", "Disbursement scheduling and tracking", "standard", None),

    # === INKOOP (gap) ===
    ("inkoop", "purchase-orders", "Inkooporders", "Purchase orders", "Create, approve, track purchase orders", "core", None),
    ("inkoop", "supplier-management", "Leveranciersbeheer", "Supplier management", "Supplier registry, performance tracking", "core", None),
    ("inkoop", "approval-workflows", "Goedkeuringsworkflows", "Approval workflows", "Multi-level purchase approval", "standard", None),
    ("inkoop", "contract-linking", "Contractkoppeling", "Contract linking", "Link purchases to contracts/frameworks", "standard", None),
    ("inkoop", "spend-analytics", "Uitgavenanalyse", "Spend analytics", "Spending reports by category/supplier", "standard", None),

    # === CONTRACTBEHEER (gap) ===
    ("contractbeheer", "contract-registry", "Contractregister", "Contract registry", "Central register of all contracts", "core", None),
    ("contractbeheer", "lifecycle-management", "Levenscyclusbeheer", "Lifecycle management", "Draft, negotiate, sign, renew, terminate", "core", None),
    ("contractbeheer", "alert-management", "Signaleringsbeheer", "Alert management", "Alerts for expiration, renewal, milestones", "standard", None),
    ("contractbeheer", "obligation-tracking", "Verplichtingenbewaking", "Obligation tracking", "Track contractual obligations and SLAs", "standard", None),
    ("contractbeheer", "document-storage", "Documentopslag", "Document storage", "Store contract PDFs with versioning", "standard", None),

    # === PLANNING (gap) ===
    ("planning", "appointment-scheduling", "Afsprakenbeheer", "Appointment scheduling", "Online appointment booking for citizens", "core", None),
    ("planning", "resource-scheduling", "Resourceplanning", "Resource scheduling", "Room, equipment, vehicle scheduling", "core", None),
    ("planning", "calendar-integration", "Agenda-integratie", "Calendar integration", "Sync with Outlook/Google/Nextcloud Calendar", "standard", None),
    ("planning", "waiting-list", "Wachtlijstbeheer", "Waiting list management", "Queue management for services", "standard", None),
    ("planning", "notifications-reminders", "Herinneringen", "Reminders", "SMS/email appointment reminders", "standard", None),

    # === BI-REPORTING (gap) ===
    ("bi-reporting", "data-visualization", "Datavisualisatie", "Data visualization", "Charts, graphs, pivot tables", "core", None),
    ("bi-reporting", "report-builder", "Rapportbouwer", "Report builder", "Drag-and-drop report creation", "core", None),
    ("bi-reporting", "data-connections", "Dataverbindingen", "Data connections", "Connect to databases, APIs, files", "core", None),
    ("bi-reporting", "scheduled-reports", "Geplande rapporten", "Scheduled reports", "Automated report generation and delivery", "standard", None),
    ("bi-reporting", "drill-down", "Doorklikkracht", "Drill-down", "Click through from summary to detail", "standard", None),
    ("bi-reporting", "export-formats", "Exportformaten", "Export formats", "PDF, Excel, CSV export", "standard", None),
]


def seed_features(conn):
    """Insert standard features, skip existing."""
    inserted = 0
    for row in STANDARD_FEATURES:
        try:
            conn.execute("""INSERT OR IGNORE INTO category_features
                (category_slug, feature_slug, feature_name_nl, feature_name_en,
                 description, priority, spec_layer)
                VALUES (?, ?, ?, ?, ?, ?, ?)""", row)
            if conn.execute("SELECT changes()").fetchone()[0] > 0:
                inserted += 1
        except Exception as e:
            print(f"  ERROR: {row[0]}/{row[1]}: {e}")
    return inserted


def backfill_tender_sources(conn):
    """Create feature_sources from existing requirements, matching to category_features."""
    # Get all category features
    features = conn.execute("""
        SELECT cf.category_slug, cf.feature_slug, cf.feature_name_nl, cf.feature_name_en
        FROM category_features cf
    """).fetchall()

    # Build keyword mapping: feature_slug -> keywords to match in requirement categories
    FEATURE_KEYWORDS = {
        "case-lifecycle": ["zaakgericht", "case management", "zaaklevenscyclus", "zaakregistratie", "zaakbehandeling"],
        "case-types": ["zaaktypeconfiguratie", "zaaktype", "ztc"],
        "zgw-api": ["zgw", "zaken api", "documenten api", "catalogi api"],
        "document-linking": ["document management", "documentbeheer", "documentkoppeling"],
        "workflow-automation": ["workflow", "procesautomatisering", "bpmn", "cmmn"],
        "task-management": ["taak", "werkvoorraad", "task"],
        "search-filter": ["zoeken", "filteren", "search"],
        "rbac": ["autorisatie", "gebruikersbeheer", "rollen", "access"],
        "audit-trail": ["audittrail", "audit", "logging", "mutatie"],
        "archiving": ["archivering", "vernietiging", "recordmanagement", "archiefwet", "bewaar"],
        "forms-intake": ["formulieren", "intake", "e-formulier", "forms"],
        "reporting": ["rapportage", "dashboard", "reporting", "bi"],
        "notifications": ["notificatie", "communicatie", "signalering", "notification"],
        "geo-location": ["geografi", "geo", "locatie", "kaart", "gis", "wms"],
        "brp-integration": ["brp", "basisregistratie personen"],
        "kvk-integration": ["kvk", "kamer van koophandel", "nhr"],
        "digid-auth": ["digid", "eherkenning", "authenticatie"],
        "document-generation": ["documentcreatie", "sjabloon", "template", "xential"],
        "sub-cases": ["deelzaak", "vervolgzaak", "sub-case"],
        "mobile-access": ["mobiel", "tablet", "mobile"],
        "common-ground": ["common ground", "nlx", "haven"],
        "permit-management": ["vergunning", "permit"],
        "inspection-management": ["toezicht", "inspectie", "inspection"],
        "enforcement": ["handhaving", "enforcement", "sanctie"],
        "dso-integration": ["dso", "omgevingswet"],
        "mobile-inspection": ["mobiel toezicht", "mobile inspection"],
        "contact-management": ["contactbeheer", "klantcontact", "contact management"],
        "interaction-tracking": ["interactie", "klantinteractie", "interaction"],
        "pipeline-management": ["pipeline", "kanban", "funnel"],
        "omnichannel": ["omnichannel", "multichannel", "kanaal"],
        "version-control": ["versiebeheer", "version"],
        "digital-signing": ["onderteken", "signing", "pkioverheid"],
        "retention-policies": ["bewaarbeleid", "retention", "selectielijst"],
        "full-text-search": ["volledige tekst", "full-text", "ocr"],
    }

    sources_added = 0

    # For each requirement, try to match to a feature
    requirements = conn.execute("""
        SELECT r.id, r.tender_id, r.category, r.type, r.text_nl,
               t.publicatie_id, t.name, t.category_slug, t.tender_url
        FROM requirements r
        JOIN tenders t ON r.tender_id = t.id
        WHERE t.category_slug IS NOT NULL OR t.relevance IS NOT NULL
    """).fetchall()

    for req_id, tender_id, req_cat, req_type, text_nl, pub_id, tender_name, cat_slug, tender_url in requirements:
        if not req_cat:
            continue

        cat_lower = req_cat.lower()
        text_lower = (text_nl or "").lower()
        combined = cat_lower + " " + text_lower

        for feature_slug, keywords in FEATURE_KEYWORDS.items():
            if any(kw in combined for kw in keywords):
                # Determine category_slug for the feature
                # Check if this feature exists for any relevant category
                feat_cat = conn.execute("""
                    SELECT category_slug FROM category_features
                    WHERE feature_slug = ? LIMIT 1
                """, (feature_slug,)).fetchone()

                if not feat_cat:
                    continue

                source_type = "tender-eis" if req_type == "eis" else "tender-wens"
                source_url = tender_url or f"https://www.tenderned.nl/aankondigingen/overzicht/{pub_id}"

                # Check if already exists
                existing = conn.execute("""
                    SELECT id FROM feature_sources
                    WHERE feature_slug = ? AND source_type = ? AND tender_id = ?
                    LIMIT 1
                """, (feature_slug, source_type, tender_id)).fetchone()

                if not existing:
                    conn.execute("""INSERT INTO feature_sources
                        (category_slug, feature_slug, source_type, source_url,
                         source_label, source_detail, tender_id, requirement_id)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)""",
                        (feat_cat[0], feature_slug, source_type, source_url,
                         f"Tender {pub_id}: {tender_name[:60]}",
                         f"{req_type.upper()}: {req_cat}",
                         tender_id, req_id))
                    sources_added += 1

    return sources_added


def backfill_competitor_sources(conn):
    """Create feature_sources from existing competitor_features."""
    sources_added = 0

    comp_features = conn.execute("""
        SELECT cf.id, cf.competitor_id, cf.feature_name, cf.category, cf.spec_path,
               c.name, c.product_line, c.url, c.slug
        FROM competitor_features cf
        JOIN competitors c ON cf.competitor_id = c.id
    """).fetchall()

    # Map competitor feature categories to our feature slugs
    COMP_FEATURE_MAP = {
        "architecture": "common-ground",
        "rest-api": "crud-api",
        "access-control": "rbac",
        "authentication": "digid-auth",
        "data-modeling": "schema-management",
        "data-model": "schema-management",
        "document-management": "document-linking",
        "notifications": "notifications",
        "notification-system": "notifications",
        "task-management": "task-management",
        "case-management": "case-lifecycle",
        "workflow-engine": "workflow-automation",
        "webhooks": "webhooks",
        "versioning": "version-control",
        "sharing": "collaborative-editing",
        "pipeline-management": "pipeline-management",
        "sales-pipeline": "pipeline-management",
        "permissions-rbac": "rbac",
        "plugin-system": "common-ground",
        "ai-features": "sentiment-analysis",
    }

    for cf_id, comp_id, feat_name, category, spec_path, comp_name, product_line, comp_url, comp_slug in comp_features:
        feature_slug = COMP_FEATURE_MAP.get(category)
        if not feature_slug:
            continue

        # Find the category this feature belongs to
        feat_cat = conn.execute("""
            SELECT category_slug FROM category_features
            WHERE feature_slug = ? LIMIT 1
        """, (feature_slug,)).fetchone()
        if not feat_cat:
            continue

        source_url = comp_url or f"https://github.com/{comp_slug}"

        existing = conn.execute("""
            SELECT id FROM feature_sources
            WHERE feature_slug = ? AND source_type = 'competitor' AND competitor_id = ?
            LIMIT 1
        """, (feature_slug, comp_id)).fetchone()

        if not existing:
            conn.execute("""INSERT INTO feature_sources
                (category_slug, feature_slug, source_type, source_url,
                 source_label, source_detail, competitor_id)
                VALUES (?, ?, 'competitor', ?, ?, ?, ?)""",
                (feat_cat[0], feature_slug, source_url,
                 f"Competitor: {comp_name}",
                 f"Feature: {feat_name} ({product_line})",
                 comp_id))
            sources_added += 1

    return sources_added


def main():
    conn = sqlite3.connect(DB_PATH)
    conn.execute("PRAGMA foreign_keys=ON")

    print("Seeding standard features per category...")
    inserted = seed_features(conn)
    print(f"  Inserted: {inserted} features")

    print("\nBackfilling tender sources...")
    tender_sources = backfill_tender_sources(conn)
    print(f"  Added: {tender_sources} tender source links")

    print("\nBackfilling competitor sources...")
    comp_sources = backfill_competitor_sources(conn)
    print(f"  Added: {comp_sources} competitor source links")

    conn.commit()

    # Summary
    print("\n=== Summary ===")
    for row in conn.execute("""
        SELECT cf.category_slug, COUNT(DISTINCT cf.feature_slug) as features,
               COUNT(DISTINCT fs.id) as sources
        FROM category_features cf
        LEFT JOIN feature_sources fs ON fs.feature_slug = cf.feature_slug
            AND fs.category_slug = cf.category_slug
        GROUP BY cf.category_slug
        ORDER BY features DESC
    """):
        print(f"  {row[0]:20s}: {row[1]:3d} features, {row[2]:4d} sources")

    print("\nSources by type:")
    for row in conn.execute("SELECT source_type, COUNT(*) FROM feature_sources GROUP BY source_type ORDER BY COUNT(*) DESC"):
        print(f"  {row[0]:15s}: {row[1]}")

    conn.close()


if __name__ == "__main__":
    main()
