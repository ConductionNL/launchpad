#!/usr/bin/env python3
"""Import Dutch government open standards from Forum Standaardisatie XML exports.

Pulls verplicht (pas toe of leg uit), aanbevolen, and in-behandeling lists.
Each standard becomes a feature source linked to relevant software categories.

Usage:
    python3 sync_forum_standaardisatie.py [db_path]
"""

import json
import os
import re
import sqlite3
import sys
import urllib.request

DB_PATH = sys.argv[1] if len(sys.argv) > 1 else "concurrentie-analyse/intelligence.db"

EXPORTS = {
    "pas-toe-of-leg-uit": "https://www.forumstandaardisatie.nl/export-standaarden/xml/verplicht",
    "aanbevolen": "https://www.forumstandaardisatie.nl/export-standaarden/xml/aanbevolen",
    "in-behandeling": "https://www.forumstandaardisatie.nl/export-standaarden/xml/in-behandeling",
}

# Map standards to our software categories by keyword matching
# Standards can be relevant to multiple categories
STANDARD_CATEGORY_MAP = {
    # API & Integration
    "rest-api design rules": ["api-management", "ipaas", "openconnector"],
    "openapi specification": ["api-management", "openconnector"],
    "digikoppeling": ["api-management", "ipaas", "openconnector"],
    "nl gov profile for cloudevents": ["ipaas", "openconnector"],
    "nl gov assurance profile for oauth": ["iam", "api-management"],
    "stuf": ["ipaas", "openconnector"],
    "soap": ["ipaas", "openconnector"],

    # Authentication & Security
    "authenticatie-standaarden": ["iam"],
    "openid": ["iam"],
    "saml": ["iam"],
    "nen-iso/iec 27001": ["iam", "zaaksysteem", "crm", "dms"],
    "nen-iso/iec 27002": ["iam", "zaaksysteem", "crm", "dms"],
    "tls": ["iam", "api-management"],
    "scim": ["iam"],
    "totp": ["iam"],
    "oauth": ["iam", "api-management"],

    # Documents & Content
    "odf": ["dms", "document-generation"],
    "pdf": ["dms", "document-generation", "data-anonymization"],
    "ades baseline profiles": ["document-generation", "data-anonymization"],
    "etsi ts 119 312": ["document-generation"],
    "cmis": ["dms"],
    "epub": ["dms", "document-generation"],

    # Data & Catalogues
    "dcat": ["data-catalogue"],
    "dcat-ap-nl": ["data-catalogue"],
    "rdf": ["data-catalogue", "objectregistratie"],
    "rdfa": ["data-catalogue"],
    "rdfs": ["data-catalogue"],
    "skos": ["data-catalogue", "objectregistratie"],
    "shacl": ["data-catalogue", "objectregistratie"],
    "nl-sbb": ["data-catalogue", "objectregistratie"],
    "tooi": ["data-catalogue"],
    "mdto": ["archivering", "dms"],
    "json": ["api-management", "objectregistratie"],
    "xml": ["api-management", "ipaas"],
    "csv": ["objectregistratie", "bi-reporting"],
    "genericode": ["objectregistratie"],

    # Geographic
    "geo-standaarden": ["gis"],
    "wfs": ["gis"],
    "wms": ["gis"],
    "imbor": ["gis"],

    # Financial
    "nlcius": ["boekhouding", "e-facturatie"],
    "peppol": ["boekhouding", "e-facturatie"],
    "ubl": ["boekhouding", "e-facturatie"],
    "xbrl": ["boekhouding", "bi-reporting"],
    "nen-iso 4217": ["boekhouding"],

    # Case management & Legal
    "bwb": ["zaaksysteem"],
    "ecli": ["zaaksysteem"],
    "jcdr": ["zaaksysteem"],
    "eml_nl": ["zaaksysteem"],

    # HRM
    "setu": ["hrm"],
    "e-portfolio nl": ["hrm"],

    # Accessibility
    "digitoegankelijk": ["ontwerp"],
    "wcag": ["ontwerp"],
    "en 301 549": ["ontwerp"],
    "pdf/ua": ["ontwerp", "document-generation"],

    # Calendar & Contacts
    "icalendar": ["planning"],
    "caldav": ["planning"],
    "webdav": ["dms"],
    "vcf": ["crm"],

    # Web
    "html": ["website-cms"],
    "css": ["website-cms", "ontwerp"],

    # IT Asset Management
    "mim": ["itam", "objectregistratie"],

    # Cloud security
    "nvn-cen/ts 18026": ["iam"],
}


def fetch_xml(url):
    """Download XML export from Forum Standaardisatie."""
    req = urllib.request.Request(url, headers={"User-Agent": "ConductionIntelligence/1.0"})
    with urllib.request.urlopen(req, timeout=60) as resp:
        return resp.read().decode('utf-8')


def parse_standards(xml_content, lijst_type):
    """Parse standards from XML content."""
    standards = []
    for block in xml_content.split('<Standaard>')[1:]:
        def extract(tag):
            match = re.search(f'<{tag}>(.*?)</{tag}>', block, re.DOTALL)
            return match.group(1).strip() if match else ""

        name = extract('Naam')
        if not name:
            continue

        standards.append({
            'name': name,
            'description': extract('Omschrijving'),
            'full_name': extract('Volledige_naam'),
            'version': extract('Versie'),
            'org': extract('Beheerorganisatie'),
            'domain': extract('Domein'),
            'keywords': extract('Trefwoorden'),
            'scope': extract('Functioneel_toepassingsgebied'),
            'list': lijst_type,
            'url': f"https://www.forumstandaardisatie.nl/open-standaarden/{name.lower().replace(' ', '-').replace('/', '-')}",
        })

    return standards


def match_categories(standard):
    """Find which of our software categories this standard is relevant to."""
    name_lower = standard['name'].lower()
    desc_lower = (standard['description'] + ' ' + standard['keywords'] + ' ' + standard['scope']).lower()

    categories = set()
    for keyword, cats in STANDARD_CATEGORY_MAP.items():
        if keyword in name_lower or keyword in desc_lower:
            categories.update(cats)

    return list(categories)


def main():
    conn = sqlite3.connect(DB_PATH)

    # Create standards tracking table
    conn.executescript("""
        CREATE TABLE IF NOT EXISTS nl_standards (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT UNIQUE NOT NULL,
            full_name TEXT,
            description TEXT,
            version TEXT,
            org TEXT,
            domain TEXT,
            keywords TEXT,
            scope TEXT,
            list_type TEXT,
            url TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP
        );
        CREATE INDEX IF NOT EXISTS idx_nl_std_name ON nl_standards(name);
        CREATE INDEX IF NOT EXISTS idx_nl_std_list ON nl_standards(list_type);
    """)

    total_standards = 0
    new_standards = 0
    new_sources = 0
    errors = []

    for lijst_type, url in EXPORTS.items():
        try:
            xml = fetch_xml(url)
            standards = parse_standards(xml, lijst_type)

            for std in standards:
                total_standards += 1

                # Upsert standard
                existing = conn.execute("SELECT id FROM nl_standards WHERE name = ?", (std['name'],)).fetchone()
                if existing:
                    conn.execute("""UPDATE nl_standards SET
                        description=?, version=?, org=?, domain=?, keywords=?,
                        scope=?, list_type=?, url=?, updated_at=datetime('now')
                        WHERE name=?""",
                        (std['description'], std['version'], std['org'], std['domain'],
                         std['keywords'], std['scope'], std['list'], std['url'], std['name']))
                else:
                    conn.execute("""INSERT INTO nl_standards
                        (name, full_name, description, version, org, domain, keywords, scope, list_type, url)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)""",
                        (std['name'], std['full_name'], std['description'], std['version'],
                         std['org'], std['domain'], std['keywords'], std['scope'], std['list'], std['url']))
                    new_standards += 1

                # Map to feature sources
                categories = match_categories(std)
                list_label = {"pas-toe-of-leg-uit": "VERPLICHT", "aanbevolen": "Aanbevolen", "in-behandeling": "In behandeling"}
                label_prefix = list_label.get(std['list'], std['list'])

                for cat in categories:
                    # Link to all features in this category
                    features = conn.execute(
                        "SELECT feature_slug FROM category_features WHERE category_slug = ?",
                        (cat,)).fetchall()

                    for (feat_slug,) in features:
                        cur = conn.execute("""
                            INSERT OR IGNORE INTO feature_sources
                            (category_slug, feature_slug, source_type, source_url,
                             source_label, source_detail)
                            VALUES (?, ?, 'standard', ?, ?, ?)
                        """, (cat, feat_slug, std['url'],
                              f"Forum Standaardisatie [{label_prefix}]: {std['name']}",
                              f"{std['description']}. Versie: {std['version']}. Beheer: {std['org']}. Domein: {std['domain']}"))
                        if cur.rowcount > 0:
                            new_sources += 1

        except Exception as e:
            errors.append(f"{lijst_type}: {str(e)[:100]}")

    conn.commit()
    conn.close()

    print(json.dumps({
        "records": total_standards,
        "new": new_standards,
        "new_sources": new_sources,
        "errors": errors
    }))


if __name__ == "__main__":
    main()
