#!/usr/bin/env python3
"""Import GEMMA architecture model from ArchiMate XML into intelligence.db.

Parses the GEMMA release.xml (ArchiMate 3.0 Open Exchange Format) and imports:
- ApplicationComponents -> gemma_components (169 referentiecomponenten + 52 external + 33 other)
- ApplicationServices -> gemma_services (422 features/functions)
- BusinessFunctions -> gemma_business_functions (296 municipal capabilities)
- BusinessProcesses -> gemma_business_processes (176 work processes)
- Relationships: component<->service, component<->function

Also maps components to our software_categories and creates feature_sources
with GEMMA Online URLs for provenance tracking.
"""

import os
import sqlite3
import xml.etree.ElementTree as ET
from collections import defaultdict

SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
DB_PATH = os.path.join(SCRIPT_DIR, "..", "intelligence.db")
GEMMA_XML = os.path.join(
    SCRIPT_DIR, "..", "..", "softwarecatalog", "data", "GEMMA release.xml"
)

NS = {"a": "http://www.opengroup.org/xsd/archimate/3.0/"}
XSI_TYPE = "{http://www.w3.org/2001/XMLSchema-instance}type"

# Mapping from our software_categories slugs to GEMMA referentiecomponent names
GEMMA_CATEGORY_MAP = {
    "zaaksysteem": [
        "Generiek zaakafhandelcomponent",
        "Zaakregistratiecomponent",
        "Zaaktypecataloguscomponent",
        "Bezwaar- en beroepcomponent",
        "Bestuurlijk activiteiten bewakingcomponent",
    ],
    "vth": [
        "Vergunning- Toezicht- Handhavingcomponent",
        "Vergunning- Toezicht- en Handhavingcomponent fysieke leefomgeving",
        "Mobiel-toezicht-en-handhavingcomponent",
        "Toezicht- en handhavingcomponent sociaal domein",
        "Inspectiecomponent",
        "Keuringcomponent",
    ],
    "dms": [
        "Documentbeheercomponent",
        "Documentcreatiecomponent",
        "Documentregistratiecomponent",
        "Scanning-en-imagingcomponent",
        "Outputmanagementcomponent",
        "Media-behandelingcomponent",
    ],
    "crm": [
        "Relatiebeheercomponent (CRM)",
        "Klachten- en meldingencomponent",
        "Klanttevredenheidcomponent",
        "Klantfeedbackcomponent",
        "Klantgeleidingcomponent",
        "Callcentercomponent",
        "Mediamonitor- en webcarecomponent",
        "Sociale mediacomponent",
    ],
    "objectregistratie": [
        "Gegevensmagazijncomponent",
        "Gegevensdistributiecomponent",
        "Kernregistratiecomponent",
        "Terugmeldingen-registratiecomponent",
        "Bedrijven- en instellingen-registratiecomponent",
        "Serviceregistercomponent",
    ],
    "formulieren": [
        "E-formulieren publicatie-en-beheercomponent",
        "Toepasbare regelscomponent",
        "Zelfdiagnosecomponent",
    ],
    "archivering": [
        "Archiefbeheercomponent",
        "Archiefportaalcomponent",
        "Archiefregistratiecomponent",
    ],
    "gis": [
        "Geo-gegevens analysecomponent",
        "Geo-gegevens inwincomponent",
        "BAG-beheercomponent",
        "BGT-beheercomponent",
        "BOR-component",
        "WOZ-beheercomponent",
        "WOZ-taxatiecomponent",
    ],
    "hrm": [
        "Personeelsinformatiecomponent",
        "Salarisadministratie en -verwerkingcomponent",
        "Medewerker-registratiecomponent",
        "Tijdregistratiecomponent",
        "Roosterbeheercomponent",
    ],
    "boekhouding": [
        "Financieel component",
        "Belastingencomponent",
        "Inningencomponent",
        "Kascomponent",
        "Onlinebetalingcomponent",
    ],
    "dashboard": [
        "BI-component",
    ],
    "bi-reporting": [
        "Data-warehousecomponent",
        "Data-laad-en-transformatiecomponent",
        "Modelbeheercomponent",
    ],
    "projectmanagement": [
        "Projectmanagementcomponent",
        "Planning en control component",
    ],
    "website-cms": [
        "Webcontentpublicatie- en beheercomponent",
        "Intranetcomponent",
    ],
    "catalogus": [
        "Producten-en-dienstencataloguscomponent",
        "Open-data-portaalcomponent",
    ],
    "integratie": [
        "Gemeentelijke servicebuscomponent",
        "Notificatierouteringcomponent",
    ],
    "iam": [
        "Gebruikersbeheercomponent",
        "Digitale-handtekeningcomponent",
        "Verwerkingenloggingcomponent",
        "Verwerkingenregistercomponent",
    ],
    "meldingen": [
        "Meldingen openbare ruimtecomponent",
        "Klachten- en meldingencomponent",
    ],
    "subsidies": [
        "Subsidiecomponent",
    ],
    "inkoop": [
        "Inkoopcomponent",
        "Bestekkencomponent",
    ],
    "contractbeheer": [
        "Contractbeheercomponent",
    ],
    "planning": [
        "Afsprakenbeheercomponent",
        "Facilitair reserveer- en uitleencomponent",
    ],
    "participatie": [
        "Cocreatiecomponent",
        "Ideeëncomponent",
    ],
    "e-facturatie": [
        "Financieel component",
    ],
    "softwarecatalogus": [
        "Architectuur- en ontwerpcomponent",
    ],
}


def parse_elements(root):
    """Parse all ArchiMate elements into a dictionary."""
    elements = {}
    for elem in root.findall(".//a:element", NS):
        eid = elem.get("identifier")
        etype = elem.get(XSI_TYPE)
        name_el = elem.find("a:name", NS)
        doc_el = elem.find("a:documentation", NS)

        props = {}
        for prop in elem.findall(".//a:property", NS):
            ref = prop.get("propertyDefinitionRef")
            val = prop.find("a:value", NS)
            if val is not None and val.text:
                props[ref] = val.text

        object_id = props.get("propid-2", "")
        gemma_url = props.get("propid-10", "")
        if not gemma_url and object_id:
            gemma_url = f"https://gemmaonline.nl/index.php/GEMMA/{eid}"

        elements[eid] = {
            "name": name_el.text if name_el is not None else "?",
            "type": etype,
            "desc": doc_el.text if doc_el is not None and doc_el.text else "",
            "object_id": object_id,
            "gemma_url": gemma_url,
            "gemma_type": props.get("propid-3", ""),
            "domain": props.get("propid-62", ""),
            "status": props.get("propid-23", ""),
        }
    return elements


def import_elements(conn, elements):
    """Insert elements into their respective tables."""
    counts = defaultdict(int)

    for eid, e in elements.items():
        if e["type"] == "ApplicationComponent":
            conn.execute(
                """INSERT OR IGNORE INTO gemma_components
                (archimate_id, object_id, name, description, gemma_type, gemma_url, domain, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)""",
                (eid, e["object_id"], e["name"], e["desc"],
                 e["gemma_type"], e["gemma_url"], e["domain"], e["status"]),
            )
            counts["components"] += 1

        elif e["type"] == "ApplicationService":
            conn.execute(
                """INSERT OR IGNORE INTO gemma_services
                (archimate_id, object_id, name, description, gemma_url, domain)
                VALUES (?, ?, ?, ?, ?, ?)""",
                (eid, e["object_id"], e["name"], e["desc"], e["gemma_url"], e["domain"]),
            )
            counts["services"] += 1

        elif e["type"] == "BusinessFunction":
            conn.execute(
                """INSERT OR IGNORE INTO gemma_business_functions
                (archimate_id, object_id, name, description, gemma_url, domain)
                VALUES (?, ?, ?, ?, ?, ?)""",
                (eid, e["object_id"], e["name"], e["desc"], e["gemma_url"], e["domain"]),
            )
            counts["business_functions"] += 1

        elif e["type"] == "BusinessProcess":
            conn.execute(
                """INSERT OR IGNORE INTO gemma_business_processes
                (archimate_id, object_id, name, description, gemma_url, domain)
                VALUES (?, ?, ?, ?, ?, ?)""",
                (eid, e["object_id"], e["name"], e["desc"], e["gemma_url"], e["domain"]),
            )
            counts["business_processes"] += 1

    conn.commit()
    return counts


def import_relationships(conn, root, elements):
    """Import ArchiMate relationships between elements."""
    # Build DB ID lookups
    comp_db = {r[1]: r[0] for r in conn.execute("SELECT id, archimate_id FROM gemma_components")}
    svc_db = {r[1]: r[0] for r in conn.execute("SELECT id, archimate_id FROM gemma_services")}
    bf_db = {r[1]: r[0] for r in conn.execute("SELECT id, archimate_id FROM gemma_business_functions")}

    comp_svc = 0
    comp_func = 0

    for rel in root.findall(".//a:relationship", NS):
        rtype = rel.get(XSI_TYPE)
        source = rel.get("source")
        target = rel.get("target")

        if source not in elements or target not in elements:
            continue

        src_type = elements[source]["type"]
        tgt_type = elements[target]["type"]

        # Component <-> Service
        if rtype in ("Composition", "Aggregation", "Realization", "Serving"):
            if src_type == "ApplicationComponent" and tgt_type == "ApplicationService":
                if source in comp_db and target in svc_db:
                    try:
                        conn.execute(
                            "INSERT OR IGNORE INTO gemma_component_services VALUES (?, ?, ?)",
                            (comp_db[source], svc_db[target], rtype),
                        )
                        comp_svc += 1
                    except sqlite3.IntegrityError:
                        pass
            elif tgt_type == "ApplicationComponent" and src_type == "ApplicationService":
                if target in comp_db and source in svc_db:
                    try:
                        conn.execute(
                            "INSERT OR IGNORE INTO gemma_component_services VALUES (?, ?, ?)",
                            (comp_db[target], svc_db[source], rtype),
                        )
                        comp_svc += 1
                    except sqlite3.IntegrityError:
                        pass

        # Component <-> BusinessFunction
        if rtype in ("Serving", "Realization"):
            if src_type == "ApplicationComponent" and tgt_type == "BusinessFunction":
                if source in comp_db and target in bf_db:
                    try:
                        conn.execute(
                            "INSERT OR IGNORE INTO gemma_component_functions VALUES (?, ?, ?)",
                            (comp_db[source], bf_db[target], rtype),
                        )
                        comp_func += 1
                    except sqlite3.IntegrityError:
                        pass
            elif src_type == "BusinessFunction" and tgt_type == "ApplicationComponent":
                if target in comp_db and source in bf_db:
                    try:
                        conn.execute(
                            "INSERT OR IGNORE INTO gemma_component_functions VALUES (?, ?, ?)",
                            (comp_db[target], bf_db[source], rtype),
                        )
                        comp_func += 1
                    except sqlite3.IntegrityError:
                        pass

    conn.commit()
    return comp_svc, comp_func


def map_categories(conn):
    """Map GEMMA components to our software_categories."""
    mapped = 0
    for cat_slug, comp_names in GEMMA_CATEGORY_MAP.items():
        for comp_name in comp_names:
            cur = conn.execute(
                "UPDATE gemma_components SET category_slug = ? WHERE name = ? AND category_slug IS NULL",
                (cat_slug, comp_name),
            )
            mapped += cur.rowcount
    conn.commit()
    return mapped


def create_feature_sources(conn):
    """Create feature_sources entries linking GEMMA services to our category_features."""
    source_count = 0

    # From component->service links
    for row in conn.execute("""
        SELECT gc.category_slug, gc.name, gc.gemma_url,
               gs.name, gs.description, gs.gemma_url
        FROM gemma_component_services gcs
        JOIN gemma_components gc ON gc.id = gcs.component_id
        JOIN gemma_services gs ON gs.id = gcs.service_id
        WHERE gc.category_slug IS NOT NULL
    """):
        cat_slug, comp_name, comp_url, svc_name, svc_desc, svc_url = row
        source_url = svc_url or comp_url or ""

        features = conn.execute(
            "SELECT feature_slug FROM category_features WHERE category_slug = ?",
            (cat_slug,),
        ).fetchall()

        for (feat_slug,) in features:
            conn.execute(
                """INSERT OR IGNORE INTO feature_sources
                (category_slug, feature_slug, source_type, source_url, source_label, source_detail)
                VALUES (?, ?, 'gemma', ?, ?, ?)""",
                (cat_slug, feat_slug, source_url,
                 f"GEMMA: {comp_name}", f"Service: {svc_name}"),
            )
            source_count += 1

    # From components themselves (referentiecomponenten)
    for row in conn.execute("""
        SELECT gc.category_slug, gc.name, gc.description, gc.gemma_url, gc.object_id
        FROM gemma_components gc
        WHERE gc.category_slug IS NOT NULL AND gc.gemma_type = 'Referentiecomponent'
    """):
        cat_slug, name, desc, url, obj_id = row
        gemma_url = url
        if not gemma_url and obj_id:
            gemma_url = f"https://gemmaonline.nl/index.php/GEMMA/id-{obj_id}"

        features = conn.execute(
            "SELECT feature_slug FROM category_features WHERE category_slug = ?",
            (cat_slug,),
        ).fetchall()

        for (feat_slug,) in features:
            conn.execute(
                """INSERT OR IGNORE INTO feature_sources
                (category_slug, feature_slug, source_type, source_url, source_label, source_detail)
                VALUES (?, ?, 'gemma', ?, ?, ?)""",
                (cat_slug, feat_slug, gemma_url,
                 f"GEMMA Referentiecomponent: {name}",
                 (desc[:200] if desc else "")),
            )
            source_count += 1

    conn.commit()
    return source_count


def main():
    if not os.path.exists(GEMMA_XML):
        print(f"ERROR: GEMMA XML not found at {GEMMA_XML}")
        print("Expected: softwarecatalog/data/GEMMA release.xml")
        return

    if not os.path.exists(DB_PATH):
        print(f"ERROR: Database not found at {DB_PATH}")
        print("Run create_db.py first")
        return

    print(f"Parsing {GEMMA_XML}...")
    tree = ET.parse(GEMMA_XML)
    root = tree.getroot()

    conn = sqlite3.connect(DB_PATH)
    conn.execute("PRAGMA journal_mode=WAL")
    conn.execute("PRAGMA foreign_keys=ON")

    # Check if already imported
    existing = conn.execute("SELECT COUNT(*) FROM gemma_components").fetchone()[0]
    if existing > 0:
        print(f"GEMMA data already imported ({existing} components). Clearing and reimporting...")
        conn.executescript("""
            DELETE FROM gemma_component_functions;
            DELETE FROM gemma_component_services;
            DELETE FROM gemma_business_processes;
            DELETE FROM gemma_business_functions;
            DELETE FROM gemma_services;
            DELETE FROM gemma_components;
            DELETE FROM feature_sources WHERE source_type = 'gemma';
        """)

    # 1. Parse elements
    elements = parse_elements(root)
    print(f"Parsed {len(elements)} ArchiMate elements")

    # 2. Import elements
    counts = import_elements(conn, elements)
    print(f"Imported: {counts['components']} components, {counts['services']} services, "
          f"{counts['business_functions']} business functions, {counts['business_processes']} processes")

    # 3. Import relationships
    comp_svc, comp_func = import_relationships(conn, root, elements)
    print(f"Linked: {comp_svc} component-service, {comp_func} component-function relationships")

    # 4. Map to our categories
    mapped = map_categories(conn)
    print(f"Mapped {mapped} components to software categories")

    # 5. Create feature sources with GEMMA URLs
    sources = create_feature_sources(conn)
    print(f"Created {sources} GEMMA feature sources")

    # Summary
    print("\n=== Import Summary ===")
    for table in ["gemma_components", "gemma_services", "gemma_business_functions",
                   "gemma_business_processes", "gemma_component_services",
                   "gemma_component_functions"]:
        count = conn.execute(f"SELECT COUNT(*) FROM {table}").fetchone()[0]
        print(f"  {table:35s} {count}")

    gemma_sources = conn.execute(
        "SELECT COUNT(*) FROM feature_sources WHERE source_type = 'gemma'"
    ).fetchone()[0]
    print(f"  {'feature_sources (gemma)':35s} {gemma_sources}")

    conn.close()
    print("\nDone.")


if __name__ == "__main__":
    main()
