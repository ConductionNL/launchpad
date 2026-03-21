#!/usr/bin/env python3
"""
Generate feature.md files per Nextcloud app from the intelligence database.

Maps each app to its software categories, then lists features from TEC taxonomy
with evidence counts and source references. Apps without TEC coverage get
features from GEMMA and other sources.

Usage:
    python3 generate_app_features.py [db_path]
"""

import os
import sqlite3
import sys
from datetime import datetime

DB_PATH = sys.argv[1] if len(sys.argv) > 1 else os.path.join(
    os.path.dirname(os.path.dirname(os.path.abspath(__file__))), "intelligence.db")

OUTPUT_DIR = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), "apps")

# App definitions: which software categories each app covers
APPS = {
    "openregister": {
        "name": "OpenRegister",
        "description": "Core data registration platform for structured data management. Foundation for all Conduction apps.",
        "categories": ["objectregistratie", "database-software"],
        "tec_categories": [],  # No direct TEC match
        "custom_note": "OpenRegister is a generic data platform — its features are defined by the schemas and registers configured on it, not by a single software category. Features below come from GEMMA referentiecomponenten and tender requirements for objectregistratie.",
    },
    "opencatalogi": {
        "name": "OpenCatalogi",
        "description": "Federated catalogue platform for open data, DCAT-AP compliance, and metadata management.",
        "categories": ["catalogus"],
        "tec_categories": [],
        "custom_note": "No direct TEC RFP template exists for federated catalogues. Features sourced from GEMMA, EU Interoperable Europe, and Dutch tender requirements.",
    },
    "openconnector": {
        "name": "OpenConnector",
        "description": "API gateway and integration platform — protocol translation, data synchronization, event bus.",
        "categories": ["integratie"],
        "tec_categories": [],
        "custom_note": "Integration middleware is partially covered by TEC's ERP integration modules. Features below are primarily from GEMMA and tender requirements.",
    },
    "docudesk": {
        "name": "Docudesk",
        "description": "Document management — storage, versioning, templates, digital signing, WOO publication, anonymization.",
        "categories": ["dms"],
        "tec_categories": ["dms"],
    },
    "pipelinq": {
        "name": "Pipelinq",
        "description": "CRM and customer interaction platform — contacts, pipelines, omnichannel communication, KCS.",
        "categories": ["crm"],
        "tec_categories": ["crm"],
    },
    "procest": {
        "name": "Procest",
        "description": "Case management (zaakafhandeling), VTH permits and enforcement, digital forms and intake.",
        "categories": ["zaaksysteem", "vth", "formulieren"],
        "tec_categories": ["zaaksysteem"],
        "custom_note": "TEC's BPM template covers process management. VTH and formulieren features come from Dutch tender requirements and GEMMA.",
    },
    "mydash": {
        "name": "MyDash",
        "description": "Dashboard system — KPI widgets, layout customization, role-based views, data source connections.",
        "categories": ["dashboard"],
        "tec_categories": [],
        "custom_note": "TEC's BI template covers dashboards as a subset. Features below come from GEMMA, G2 categories, and tender requirements.",
    },
    "nldesign": {
        "name": "NL Design",
        "description": "Design system providing NL Design System tokens, WCAG AA accessibility, and Rijkshuisstijl theming for Nextcloud.",
        "categories": ["ontwerp"],
        "tec_categories": [],
        "custom_note": "Design systems are not a standard TEC category. Features are defined by NL Design System standards, WCAG guidelines, and Rijkshuisstijl requirements.",
    },
    "softwarecatalog": {
        "name": "SoftwareCatalog",
        "description": "Software portfolio management — GEMMA architecture mapping, application landscape, compliance tracking.",
        "categories": ["softwarecatalogus"],
        "tec_categories": [],
        "custom_note": "Software catalogues are a Dutch government-specific concept (GEMMA Softwarecatalogus). Features come from GEMMA and VNG standards.",
    },
    "zaakafhandelapp": {
        "name": "ZaakAfhandelApp",
        "description": "Citizen-facing case management frontend — case status, document submission, communication.",
        "categories": ["zaaksysteem"],
        "tec_categories": ["zaaksysteem"],
        "custom_note": "Shares the zaaksysteem category with Procest but focuses on the citizen-facing interaction layer.",
    },
    "larpingapp": {
        "name": "LarpingApp",
        "description": "LARP (Live Action Role Playing) character and event management application.",
        "categories": [],
        "tec_categories": [],
        "custom_note": "LarpingApp is a domain-specific application with no standard software category mapping. Its features are defined by the LARP community's needs, not by industry frameworks.",
        "skip_features": True,
    },
}


def generate_feature_md(conn, app_id, app_info):
    """Generate a feature.md file for one app."""
    lines = []
    now = datetime.now().strftime("%Y-%m-%d")

    lines.append(f"# {app_info['name']} — Feature Reference")
    lines.append(f"")
    lines.append(f"**Application**: {app_info['name']}")
    lines.append(f"**Description**: {app_info['description']}")
    cats = app_info['categories']
    if cats:
        cat_names = []
        for c in cats:
            row = conn.execute("SELECT name_en FROM software_categories WHERE slug=?", (c,)).fetchone()
            cat_names.append(f"{row[0]} (`{c}`)" if row else c)
        lines.append(f"**Software categories**: {', '.join(cat_names)}")
    else:
        lines.append(f"**Software categories**: None (domain-specific)")
    lines.append(f"**Generated**: {now}")
    lines.append(f"")

    if app_info.get('custom_note'):
        lines.append(f"> {app_info['custom_note']}")
        lines.append(f"")

    if app_info.get('skip_features'):
        lines.append("This application has no standard software category mapping. Features are defined in the app's own openspec.")
        return '\n'.join(lines)

    # TEC features (canonical taxonomy)
    for cat in cats:
        tec_count = conn.execute("SELECT COUNT(*) FROM tec_features WHERE category_slug=?", (cat,)).fetchone()[0]

        if tec_count > 0:
            cat_name = conn.execute("SELECT name_en FROM software_categories WHERE slug=?", (cat,)).fetchone()
            lines.append(f"## {cat_name[0] if cat_name else cat} — TEC Feature Taxonomy")
            lines.append(f"")
            lines.append(f"*{tec_count} features from TEC RFP templates, with evidence from {conn.execute('SELECT COUNT(DISTINCT fe.source_type) FROM feature_evidence fe JOIN tec_features tf ON tf.id=fe.tec_feature_id WHERE tf.category_slug=?', (cat,)).fetchone()[0]} source types.*")
            lines.append(f"")

            # Get top-level modules
            modules = conn.execute("""
                SELECT DISTINCT tf.tec_code, tf.name
                FROM tec_features tf
                WHERE tf.category_slug = ? AND tf.level = 1
                ORDER BY tf.tec_code
            """, (cat,)).fetchall()

            for mod_code, mod_name in modules:
                # Get evidence for this module
                mod_evidence = conn.execute("""
                    SELECT fi.evidence_count, fi.source_diversity, fi.source_types
                    FROM feature_importance fi
                    JOIN tec_features tf ON tf.id = fi.tec_feature_id
                    WHERE tf.category_slug = ? AND tf.tec_code = ?
                """, (cat, mod_code)).fetchone()

                ev_str = ""
                if mod_evidence and mod_evidence[0] > 1:
                    ev_str = f" — {mod_evidence[0]} evidence, {mod_evidence[1]} source types"

                lines.append(f"### {mod_code}. {mod_name}{ev_str}")
                lines.append(f"")

                # Get sub-features (level 2-3)
                subs = conn.execute("""
                    SELECT fi.tec_code, fi.name, fi.evidence_count, fi.source_diversity, fi.source_types, fi.is_module
                    FROM feature_importance fi
                    JOIN tec_features tf ON tf.id = fi.tec_feature_id
                    WHERE tf.category_slug = ? AND tf.tec_code LIKE ? AND tf.level > 1
                    ORDER BY tf.tec_code
                """, (cat, f"{mod_code}.%")).fetchall()

                for sub in subs:
                    code, name, ev_count, diversity, types, is_mod = sub
                    indent = "  " * (code.count('.') - 1)
                    ev_info = ""
                    if ev_count > 1:
                        type_list = types.split(',') if types else []
                        type_badges = ' '.join(f"`{t}`" for t in type_list[:4])
                        ev_info = f" [{ev_count} evidence, {diversity} types: {type_badges}]"

                    if is_mod:
                        lines.append(f"{indent}- **{code} {name}**{ev_info}")
                    else:
                        lines.append(f"{indent}- {code} {name}{ev_info}")

                lines.append(f"")

        # Unmapped evidence (features not in TEC)
        unmapped = conn.execute("""
            SELECT source_type, potential_feature, source_url, source_detail
            FROM unmapped_evidence
            WHERE category_slug = ?
            ORDER BY source_type, potential_feature
        """, (cat,)).fetchall()

        if unmapped:
            cat_name = conn.execute("SELECT name_en FROM software_categories WHERE slug=?", (cat,)).fetchone()
            lines.append(f"## {cat_name[0] if cat_name else cat} — Additional Features (not in TEC)")
            lines.append(f"")
            lines.append(f"*{len(unmapped)} features from sources outside the TEC taxonomy.*")
            lines.append(f"")

            by_type = {}
            for stype, feat, url, detail in unmapped:
                by_type.setdefault(stype, []).append((feat, url, detail))

            for stype in sorted(by_type.keys()):
                items = by_type[stype]
                lines.append(f"### From {stype}")
                for feat, url, detail in items[:20]:  # Cap at 20 per type
                    url_link = f" ([source]({url}))" if url else ""
                    lines.append(f"- {feat}{url_link}")
                if len(items) > 20:
                    lines.append(f"- *...and {len(items) - 20} more*")
                lines.append(f"")

    # Summary stats
    total_tec = sum(conn.execute("SELECT COUNT(*) FROM tec_features WHERE category_slug=?", (c,)).fetchone()[0] for c in cats)
    total_evidence = sum(conn.execute("SELECT COUNT(*) FROM feature_evidence fe JOIN tec_features tf ON tf.id=fe.tec_feature_id WHERE tf.category_slug=?", (c,)).fetchone()[0] for c in cats)
    total_unmapped = sum(conn.execute("SELECT COUNT(*) FROM unmapped_evidence WHERE category_slug=?", (c,)).fetchone()[0] for c in cats)

    lines.append(f"---")
    lines.append(f"")
    lines.append(f"**Summary**: {total_tec} TEC features, {total_evidence} evidence links, {total_unmapped} additional (non-TEC) features")
    lines.append(f"")
    lines.append(f"*Generated from `concurrentie-analyse/intelligence.db` by `scripts/generate_app_features.py`*")

    return '\n'.join(lines)


def main():
    conn = sqlite3.connect(DB_PATH)
    os.makedirs(OUTPUT_DIR, exist_ok=True)

    for app_id, app_info in APPS.items():
        md = generate_feature_md(conn, app_id, app_info)
        filepath = os.path.join(OUTPUT_DIR, f"{app_id}.md")
        with open(filepath, 'w') as f:
            f.write(md)

        cats = app_info['categories']
        total_tec = sum(conn.execute("SELECT COUNT(*) FROM tec_features WHERE category_slug=?", (c,)).fetchone()[0] for c in cats)
        total_unmapped = sum(conn.execute("SELECT COUNT(*) FROM unmapped_evidence WHERE category_slug=?", (c,)).fetchone()[0] for c in cats)
        print(f"  {app_id:18s} → {filepath:50s} ({total_tec} TEC + {total_unmapped} other features)")

    conn.close()
    print(f"\nGenerated {len(APPS)} feature files in {OUTPUT_DIR}/")


if __name__ == "__main__":
    main()
