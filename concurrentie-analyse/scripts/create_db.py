#!/usr/bin/env python3
"""Create the intelligence SQLite database with full schema."""

import os
import sqlite3

DB_PATH = os.path.join(os.path.dirname(os.path.dirname(__file__)), "intelligence.db")

SCHEMA = """
-- Software taxonomy based on EU CPV codes + GEMMA mapping
CREATE TABLE IF NOT EXISTS software_categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    cpv_code TEXT,                       -- EU CPV code (e.g. '48000000')
    slug TEXT UNIQUE NOT NULL,           -- internal slug: 'zaaksysteem', 'crm', 'erp'
    name_nl TEXT NOT NULL,
    name_en TEXT NOT NULL,
    parent_slug TEXT,                    -- hierarchy
    gemma_reference TEXT,               -- GEMMA architecture mapping
    description TEXT,
    conduction_product TEXT,            -- NULL if gap, 'procest'/'pipelinq'/etc if covered
    created_at TEXT DEFAULT (datetime('now')),
    FOREIGN KEY (parent_slug) REFERENCES software_categories(slug)
);

-- Tenders from TenderNed / TED / manual
CREATE TABLE IF NOT EXISTS tenders (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    publicatie_id TEXT UNIQUE NOT NULL,  -- TenderNed publicatieId or TED notice ID
    source TEXT NOT NULL CHECK(source IN ('tenderned', 'ted', 'manual', 'migration')),
    name TEXT NOT NULL,
    organisation TEXT,
    publication_date TEXT,
    closing_date TEXT,
    type_publication_code TEXT,         -- 'REC', 'AGO', 'EF16', etc.
    type_publication TEXT,              -- 'Aankondiging opdracht', 'Rectificatie', etc.
    procedure_code TEXT,               -- 'OPE', 'BEP', etc.
    procedure TEXT,                     -- 'Openbaar', 'Niet-openbaar', etc.
    type_opdracht TEXT,                 -- 'Diensten', 'Leveringen', 'Werken'
    cpv_codes TEXT,                     -- JSON array of CPV codes from the tender
    description TEXT,
    is_european INTEGER DEFAULT 0,
    is_digital INTEGER DEFAULT 0,
    category_slug TEXT,                 -- FK to software_categories
    relevance TEXT,                     -- 'procest', 'pipelinq', 'both', etc.
    search_terms TEXT,                  -- JSON array of matched search terms
    status TEXT DEFAULT 'new',          -- 'new', 'downloaded', 'analysed', 'classified'
    winner TEXT,
    contract_value REAL,
    contract_duration TEXT,
    bid_count INTEGER,
    eisen_count INTEGER DEFAULT 0,
    wensen_count INTEGER DEFAULT 0,
    tender_url TEXT,
    analyse_path TEXT,                  -- relative path to ANALYSE.md if exists
    raw_json TEXT,                      -- full API response
    created_at TEXT DEFAULT (datetime('now')),
    updated_at TEXT DEFAULT (datetime('now')),
    FOREIGN KEY (category_slug) REFERENCES software_categories(slug)
);

-- Downloaded PDF documents per tender
CREATE TABLE IF NOT EXISTS tender_documents (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tender_id INTEGER NOT NULL,
    filename TEXT NOT NULL,
    doc_type TEXT,                      -- 'PvE', 'PvW', 'NvI', 'Leidraad', 'Bestek'
    page_count INTEGER,
    file_size_kb INTEGER,
    filepath TEXT,                      -- relative path from repo root
    download_url TEXT,
    downloaded INTEGER DEFAULT 0,
    analysed INTEGER DEFAULT 0,
    created_at TEXT DEFAULT (datetime('now')),
    FOREIGN KEY (tender_id) REFERENCES tenders(id) ON DELETE CASCADE
);

-- Extracted requirements (eisen + wensen)
CREATE TABLE IF NOT EXISTS requirements (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tender_id INTEGER NOT NULL,
    code TEXT,                          -- 'E1', 'W3', 'PvE-12', etc.
    category TEXT,                      -- 'zaakgericht-werken', 'document-management', etc.
    text_nl TEXT NOT NULL,
    text_en TEXT,
    type TEXT NOT NULL CHECK(type IN ('eis', 'wens')),
    weight INTEGER,                    -- wensen weighting if specified
    source_document TEXT,
    source_page TEXT,
    spec_layer TEXT,                    -- from SPEC-CLASSIFICATION: 'openregister', 'procest', etc.
    created_at TEXT DEFAULT (datetime('now')),
    FOREIGN KEY (tender_id) REFERENCES tenders(id) ON DELETE CASCADE
);

-- Integration requirements per tender
CREATE TABLE IF NOT EXISTS integrations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tender_id INTEGER NOT NULL,
    system TEXT NOT NULL,               -- 'BRP', 'BAG', 'DigiD', 'DSO', etc.
    direction TEXT,                     -- 'in', 'out', 'bi'
    protocol TEXT,                      -- 'StUF-BG 3.10', 'Haal Centraal', 'REST', etc.
    details TEXT,
    created_at TEXT DEFAULT (datetime('now')),
    FOREIGN KEY (tender_id) REFERENCES tenders(id) ON DELETE CASCADE
);

-- Competitors (from existing analyses)
CREATE TABLE IF NOT EXISTS competitors (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    product_line TEXT NOT NULL,         -- 'openregister', 'pipelinq', 'procest'
    slug TEXT UNIQUE,                   -- 'directus', 'twenty', 'dimpact-zac'
    url TEXT,
    license TEXT,
    tech_stack TEXT,
    language TEXT,
    pricing_model TEXT,                 -- 'open-source', 'freemium', 'saas', 'proprietary'
    pricing_details TEXT,
    github_url TEXT,
    github_stars INTEGER,
    community_size TEXT,
    analysis_path TEXT,                 -- path to MERGED-ANALYSIS.md
    specs_count INTEGER DEFAULT 0,
    created_at TEXT DEFAULT (datetime('now')),
    updated_at TEXT DEFAULT (datetime('now'))
);

-- Individual features per competitor
CREATE TABLE IF NOT EXISTS competitor_features (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    competitor_id INTEGER NOT NULL,
    feature_name TEXT NOT NULL,
    category TEXT,
    description TEXT,
    tier TEXT,                          -- 'free', 'premium', 'enterprise'
    spec_path TEXT,
    created_at TEXT DEFAULT (datetime('now')),
    FOREIGN KEY (competitor_id) REFERENCES competitors(id) ON DELETE CASCADE
);

-- Identified ecosystem gaps
CREATE TABLE IF NOT EXISTS ecosystem_gaps (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    category_slug TEXT NOT NULL,
    tender_count INTEGER DEFAULT 0,
    estimated_annual_value REAL,
    avg_contract_value REAL,
    our_product TEXT,                   -- NULL if true gap
    gap_status TEXT DEFAULT 'identified',
        -- 'identified', 'investigating', 'proposed', 'in-development', 'dismissed'
    gap_description TEXT,
    market_notes TEXT,
    first_spotted TEXT DEFAULT (datetime('now')),
    last_updated TEXT DEFAULT (datetime('now')),
    FOREIGN KEY (category_slug) REFERENCES software_categories(slug)
);

-- App proposals generated from gaps
CREATE TABLE IF NOT EXISTS app_proposals (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    gap_id INTEGER,
    app_name TEXT NOT NULL,
    description TEXT,
    problem_statement TEXT,
    target_market TEXT,
    estimated_market_size TEXT,
    revenue_model TEXT,
    monthly_revenue_potential TEXT,
    core_features TEXT,                 -- JSON array
    competitor_summary TEXT,            -- JSON array
    roadmap_status TEXT DEFAULT 'idea',
    proposal_path TEXT,
    created_at TEXT DEFAULT (datetime('now')),
    FOREIGN KEY (gap_id) REFERENCES ecosystem_gaps(id)
);

-- Award tracking (who wins tenders)
CREATE TABLE IF NOT EXISTS tender_awards (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tender_id INTEGER NOT NULL,
    vendor_name TEXT NOT NULL,
    contract_value REAL,
    contract_start TEXT,
    contract_duration TEXT,
    bid_rank INTEGER DEFAULT 1,
    created_at TEXT DEFAULT (datetime('now')),
    FOREIGN KEY (tender_id) REFERENCES tenders(id) ON DELETE CASCADE
);

-- Indexes for common queries
CREATE INDEX IF NOT EXISTS idx_tenders_category ON tenders(category_slug);
CREATE INDEX IF NOT EXISTS idx_tenders_source ON tenders(source);
CREATE INDEX IF NOT EXISTS idx_tenders_status ON tenders(status);
CREATE INDEX IF NOT EXISTS idx_tenders_date ON tenders(publication_date);
CREATE INDEX IF NOT EXISTS idx_tenders_relevance ON tenders(relevance);
CREATE INDEX IF NOT EXISTS idx_requirements_tender ON requirements(tender_id);
CREATE INDEX IF NOT EXISTS idx_requirements_category ON requirements(category);
CREATE INDEX IF NOT EXISTS idx_requirements_type ON requirements(type);
CREATE INDEX IF NOT EXISTS idx_requirements_layer ON requirements(spec_layer);
CREATE INDEX IF NOT EXISTS idx_integrations_system ON integrations(system);
CREATE INDEX IF NOT EXISTS idx_competitors_product ON competitors(product_line);
CREATE INDEX IF NOT EXISTS idx_competitor_features_competitor ON competitor_features(competitor_id);
CREATE INDEX IF NOT EXISTS idx_gaps_status ON ecosystem_gaps(gap_status);
CREATE INDEX IF NOT EXISTS idx_gaps_category ON ecosystem_gaps(category_slug);
CREATE INDEX IF NOT EXISTS idx_awards_vendor ON tender_awards(vendor_name);

-- Standard/expected features per software category (the feature template)
CREATE TABLE IF NOT EXISTS category_features (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    category_slug TEXT NOT NULL,
    feature_slug TEXT NOT NULL,
    feature_name_nl TEXT NOT NULL,
    feature_name_en TEXT NOT NULL,
    description TEXT,
    priority TEXT DEFAULT 'standard',    -- 'core', 'standard', 'advanced', 'optional'
    spec_layer TEXT,                     -- from SPEC-CLASSIFICATION
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(category_slug, feature_slug),
    FOREIGN KEY (category_slug) REFERENCES software_categories(slug)
);

-- Multi-source provenance: where each feature was seen
-- Source types organized by category:
--   Tenders:     tender-eis, tender-wens
--   Architecture: gemma, iso, standard
--   Competitors: competitor, github-issue, github-discussion, documentation, changelog
--   Comparison:  g2, capterra, alternativeto, sourceforge, awesome-list
--   Opinion:     blog, article, research-paper, social-media, forum, pro, con
--   Manual:      manual, tec
CREATE TABLE IF NOT EXISTS feature_sources (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    category_slug TEXT NOT NULL,
    feature_slug TEXT NOT NULL,
    source_type TEXT NOT NULL CHECK(source_type IN (
        'tender-eis', 'tender-wens',
        'gemma',
        'competitor',
        'github-issue', 'github-discussion',
        'g2', 'capterra', 'alternativeto', 'sourceforge',
        'blog', 'article', 'research-paper',
        'social-media', 'forum',
        'pro', 'con',
        'awesome-list',
        'documentation', 'changelog',
        'iso', 'standard',
        'manual', 'tec'
    )),
    sentiment TEXT CHECK(sentiment IN ('positive', 'negative', 'neutral', NULL)),
    source_url TEXT,                     -- URL to the source
    source_label TEXT,                   -- human-readable label
    source_detail TEXT,                  -- extra context
    tender_id INTEGER,
    competitor_id INTEGER,
    requirement_id INTEGER,
    frequency INTEGER DEFAULT 1,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_slug) REFERENCES software_categories(slug),
    FOREIGN KEY (tender_id) REFERENCES tenders(id),
    FOREIGN KEY (competitor_id) REFERENCES competitors(id),
    FOREIGN KEY (requirement_id) REFERENCES requirements(id)
);

CREATE INDEX IF NOT EXISTS idx_cat_features_category ON category_features(category_slug);
CREATE INDEX IF NOT EXISTS idx_cat_features_slug ON category_features(feature_slug);
CREATE INDEX IF NOT EXISTS idx_feat_sources_feature ON feature_sources(feature_slug);
CREATE INDEX IF NOT EXISTS idx_feat_sources_type ON feature_sources(source_type);
CREATE INDEX IF NOT EXISTS idx_feat_sources_category ON feature_sources(category_slug);
CREATE INDEX IF NOT EXISTS idx_feat_sources_sentiment ON feature_sources(sentiment);

-- GEMMA architecture model data (from GEMMA release.xml)
-- These tables store the full ArchiMate model so we don't need the XML file

-- GEMMA referentiecomponenten (standard software types in municipal architecture)
CREATE TABLE IF NOT EXISTS gemma_components (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    archimate_id TEXT UNIQUE NOT NULL,       -- element identifier from ArchiMate XML
    object_id TEXT,                           -- GEMMA Object ID (propid-2)
    name TEXT NOT NULL,
    description TEXT,
    gemma_type TEXT,                          -- 'Referentiecomponent', 'Buitengemeentelijke voorziening'
    gemma_url TEXT,                           -- https://gemmaonline.nl/index.php/GEMMA/{id}
    domain TEXT,
    status TEXT,
    category_slug TEXT,                       -- FK mapped to our software_categories
    FOREIGN KEY (category_slug) REFERENCES software_categories(slug)
);

-- GEMMA applicatieservices (features/functions a component provides)
CREATE TABLE IF NOT EXISTS gemma_services (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    archimate_id TEXT UNIQUE NOT NULL,
    object_id TEXT,
    name TEXT NOT NULL,
    description TEXT,
    gemma_url TEXT,
    domain TEXT
);

-- Component <-> Service relationships
CREATE TABLE IF NOT EXISTS gemma_component_services (
    component_id INTEGER NOT NULL,
    service_id INTEGER NOT NULL,
    relationship_type TEXT,                  -- 'Aggregation', 'Composition', 'Realization', 'Serving'
    PRIMARY KEY (component_id, service_id),
    FOREIGN KEY (component_id) REFERENCES gemma_components(id),
    FOREIGN KEY (service_id) REFERENCES gemma_services(id)
);

-- GEMMA bedrijfsfuncties (municipal business capabilities)
CREATE TABLE IF NOT EXISTS gemma_business_functions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    archimate_id TEXT UNIQUE NOT NULL,
    object_id TEXT,
    name TEXT NOT NULL,
    description TEXT,
    gemma_url TEXT,
    domain TEXT
);

-- Component supports business function
CREATE TABLE IF NOT EXISTS gemma_component_functions (
    component_id INTEGER NOT NULL,
    function_id INTEGER NOT NULL,
    relationship_type TEXT,
    PRIMARY KEY (component_id, function_id),
    FOREIGN KEY (component_id) REFERENCES gemma_components(id),
    FOREIGN KEY (function_id) REFERENCES gemma_business_functions(id)
);

-- GEMMA bedrijfsprocessen (actual work processes)
CREATE TABLE IF NOT EXISTS gemma_business_processes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    archimate_id TEXT UNIQUE NOT NULL,
    object_id TEXT,
    name TEXT NOT NULL,
    description TEXT,
    gemma_url TEXT,
    domain TEXT
);

CREATE INDEX IF NOT EXISTS idx_gemma_comp_name ON gemma_components(name);
CREATE INDEX IF NOT EXISTS idx_gemma_comp_category ON gemma_components(category_slug);
CREATE INDEX IF NOT EXISTS idx_gemma_svc_name ON gemma_services(name);
CREATE INDEX IF NOT EXISTS idx_gemma_bf_name ON gemma_business_functions(name);
"""

# CPV-based software categories with Conduction product mapping
SEED_CATEGORIES = [
    # CPV 48000000 tree — Software packages
    ("48000000", "software", "Software", "Software", None, None, "Top-level software category", None),
    ("48100000", "industry-software", "Branchespecifieke software", "Industry-specific software", "software", None, "Industry-specific software packages", None),
    ("48200000", "networking-software", "Netwerk- en internetsoftware", "Networking and internet software", "software", None, "Networking, internet, intranet software", None),
    ("48300000", "document-software", "Document- en publicatiesoftware", "Document and publishing software", "software", None, "Document creation, drawing, publishing software", "docudesk"),
    ("48400000", "business-software", "Bedrijfstransactiesoftware", "Business transaction software", "software", None, "Business transaction and personal software", None),
    ("48600000", "database-software", "Database- en besturingssysteemsoftware", "Database and OS software", "software", None, "Database and operating software", "openregister"),
    ("48700000", "utility-software", "Hulpprogramma's", "Software utilities", "software", None, "Software utility packages", None),
    ("72000000", "it-services", "IT-diensten", "IT services", None, None, "IT services: consulting, development, support", None),
    ("72200000", "software-development", "Softwareontwikkeling", "Software development", "it-services", None, "Software programming and consultancy", None),
    ("72260000", "software-services", "Softwaregerelateerde diensten", "Software-related services", "it-services", None, "Software-related services", None),

    # Dutch government specific (mapped to CPV where possible)
    (None, "zaaksysteem", "Zaaksysteem", "Case management system", "industry-software", "Zaakafhandelcomponent", "ZGW-compliant case management", "procest"),
    (None, "vth", "VTH-systeem", "Permits & enforcement system", "zaaksysteem", "VTH", "Vergunningen, Toezicht en Handhaving", "procest"),
    (None, "dms", "Documentmanagementsysteem", "Document management system", "document-software", "DMS", "Document management and archiving", "docudesk"),
    (None, "crm", "CRM / Klantinteractie", "CRM / Customer interaction", "business-software", "Klantinteractie", "Customer relationship and interaction management", "pipelinq"),
    (None, "objectregistratie", "Objectregistratie", "Object registration", "database-software", "Objectenregistratie", "Structured data and object registration", "openregister"),
    (None, "formulieren", "Formulierensysteem", "Forms system", "zaaksysteem", "Formulierencomponent", "Digital forms and intake", "procest"),
    (None, "catalogus", "Catalogussysteem", "Catalogue system", "database-software", "Catalogus", "Federated catalogues and open data", "opencatalogi"),
    (None, "dashboard", "Dashboardsysteem", "Dashboard system", "business-software", "Dashboard", "KPI dashboards and management reporting", "mydash"),
    (None, "softwarecatalogus", "Softwarecatalogus", "Software catalogue", "business-software", "Applicatielandschap", "Software portfolio and GEMMA compliance", "softwarecatalog"),
    (None, "integratie", "Integratieplatform", "Integration platform", "it-services", "Integratiecomponent", "API gateway, data synchronization", "openconnector"),
    (None, "ontwerp", "Ontwerpsysteem", "Design system", "software", "NL Design", "Government theming and accessibility", "nldesign"),

    # Potential ecosystem gaps (no Conduction product)
    (None, "boekhouding", "Boekhoudsoftware", "Bookkeeping software", "business-software", None, "Financial administration and bookkeeping", None),
    (None, "erp", "ERP-systeem", "ERP system", "industry-software", None, "Enterprise resource planning", None),
    (None, "hrm", "HRM-systeem", "HRM system", "industry-software", None, "Human resource management", None),
    (None, "projectmanagement", "Projectmanagementsoftware", "Project management software", "business-software", None, "Project planning and tracking", None),
    (None, "gis", "GIS-systeem", "GIS system", "industry-software", "GIS", "Geographic information system", None),
    (None, "e-facturatie", "E-facturatiesysteem", "E-invoicing system", "business-software", None, "Electronic invoicing (Peppol/UBL)", None),
    (None, "iam", "Identiteitsbeheer", "Identity & access management", "it-services", "IAM", "SSO, DigiD, eHerkenning integration", None),
    (None, "archivering", "Archiveringssysteem", "Archival system", "document-software", "Archiefbeheer", "Long-term digital archiving (Archiefwet)", None),
    (None, "website-cms", "Website CMS", "Website CMS", "document-software", "Webcontent", "Municipal website content management", None),
    (None, "participatie", "Participatieplatform", "Participation platform", "business-software", None, "Citizen participation and consultation", None),
    (None, "meldingen", "Meldingensysteem", "Reporting/notifications system", "business-software", None, "Citizen incident/complaint reporting", None),
    (None, "subsidies", "Subsidiesysteem", "Subsidy management system", "business-software", None, "Grant and subsidy administration", None),
    (None, "inkoop", "Inkoopsysteem", "Procurement system", "business-software", None, "Purchasing and procurement management", None),
    (None, "contractbeheer", "Contractbeheersysteem", "Contract management system", "business-software", None, "Contract lifecycle management", None),
    (None, "planning", "Planningssysteem", "Planning/scheduling system", "business-software", None, "Resource and appointment scheduling", None),
    (None, "bi-reporting", "BI / Rapportage", "BI / Reporting", "business-software", None, "Business intelligence and analytics", None),
]


def create_database():
    """Create database with schema and seed data."""
    db_exists = os.path.exists(DB_PATH)
    conn = sqlite3.connect(DB_PATH)
    conn.execute("PRAGMA journal_mode=WAL")
    conn.execute("PRAGMA foreign_keys=ON")
    conn.executescript(SCHEMA)

    # Seed categories only if table is empty
    cursor = conn.execute("SELECT COUNT(*) FROM software_categories")
    if cursor.fetchone()[0] == 0:
        conn.executemany(
            """INSERT INTO software_categories
               (cpv_code, slug, name_nl, name_en, parent_slug, gemma_reference, description, conduction_product)
               VALUES (?, ?, ?, ?, ?, ?, ?, ?)""",
            SEED_CATEGORIES,
        )
        print(f"Seeded {len(SEED_CATEGORIES)} software categories")

    conn.commit()
    conn.close()

    action = "Updated" if db_exists else "Created"
    print(f"{action} database: {DB_PATH}")

    # Verify
    conn = sqlite3.connect(DB_PATH)
    tables = conn.execute("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name").fetchall()
    print(f"Tables: {', '.join(t[0] for t in tables)}")
    cats = conn.execute("SELECT COUNT(*) FROM software_categories").fetchone()[0]
    covered = conn.execute("SELECT COUNT(*) FROM software_categories WHERE conduction_product IS NOT NULL").fetchone()[0]
    gaps = conn.execute("SELECT COUNT(*) FROM software_categories WHERE conduction_product IS NULL AND parent_slug IS NOT NULL AND slug NOT IN (SELECT COALESCE(parent_slug,'') FROM software_categories)").fetchone()[0]
    print(f"Categories: {cats} total, {covered} covered by Conduction products, {gaps} potential gaps")
    conn.close()


if __name__ == "__main__":
    create_database()
