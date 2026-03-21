#!/usr/bin/env python3
"""Pull open source government software from the Digital Public Goods Registry API."""

import json
import sqlite3
import sys
import urllib.request

DB_PATH = sys.argv[1] if len(sys.argv) > 1 else "concurrentie-analyse/intelligence.db"

API_URL = "https://api.digitalpublicgoods.net/dpgs"

# Map DPG categories/types to our software_categories
KEYWORD_MAP = {
    "case management": "zaaksysteem",
    "document management": "dms",
    "crm": "crm",
    "customer": "crm",
    "erp": "erp",
    "accounting": "boekhouding",
    "financial": "boekhouding",
    "human resource": "hrm",
    "project management": "projectmanagement",
    "gis": "gis",
    "geographic": "gis",
    "mapping": "gis",
    "content management": "website-cms",
    "cms": "website-cms",
    "form": "formulieren",
    "survey": "formulieren",
    "identity": "iam",
    "authentication": "iam",
    "archive": "archivering",
    "reporting": "bi-reporting",
    "analytics": "bi-reporting",
    "dashboard": "dashboard",
    "participation": "participatie",
    "voting": "participatie",
    "procurement": "inkoop",
    "subsidy": "subsidies",
    "grant": "subsidies",
    "ticketing": "meldingen",
    "complaint": "meldingen",
    "integration": "integratie",
    "interoperability": "integratie",
    "catalogue": "catalogus",
    "registry": "objectregistratie",
    "data management": "objectregistratie",
}


def fetch_dpgs():
    """Fetch all Digital Public Goods."""
    req = urllib.request.Request(API_URL, headers={"User-Agent": "ConductionIntelligence/1.0"})
    try:
        with urllib.request.urlopen(req, timeout=120) as resp:
            return json.loads(resp.read().decode())
    except Exception as e:
        print(json.dumps({"records": 0, "new": 0, "errors": [f"API unreachable: {e}"]}))
        sys.exit(0)


def classify_dpg(dpg):
    """Try to match a DPG to our software categories by keywords."""
    text = f"{dpg.get('name', '')} {dpg.get('description', '')} {' '.join(dpg.get('type', []))}".lower()
    matches = set()
    for keyword, slug in KEYWORD_MAP.items():
        if keyword in text:
            matches.add(slug)
    return list(matches)


def main():
    dpgs = fetch_dpgs()
    conn = sqlite3.connect(DB_PATH)

    new_count = 0
    matched_dpgs = 0

    for dpg in dpgs:
        name = dpg.get("name", "")
        description = dpg.get("description", "")
        dpg_url = dpg.get("website", "") or f"https://digitalpublicgoods.net/registry/{name.lower().replace(' ', '-')}"
        repo_url = ""
        repos = dpg.get("repositories", [])
        if repos and isinstance(repos, list) and len(repos) > 0:
            repo_url = repos[0].get("url", "") if isinstance(repos[0], dict) else str(repos[0])

        categories = classify_dpg(dpg)
        if not categories:
            continue

        matched_dpgs += 1

        for cat_slug in categories:
            features = conn.execute(
                "SELECT feature_slug FROM category_features WHERE category_slug = ?",
                (cat_slug,)
            ).fetchall()

            source_url = repo_url or dpg_url
            for (feat_slug,) in features:
                cur = conn.execute("""
                    INSERT OR IGNORE INTO feature_sources
                    (category_slug, feature_slug, source_type, source_url,
                     source_label, source_detail, sentiment)
                    VALUES (?, ?, 'standard', ?, ?, ?, 'positive')
                """, (cat_slug, feat_slug, source_url,
                      f"DPG: {name}",
                      f"{description[:200]}"))
                if cur.rowcount > 0:
                    new_count += 1

    conn.commit()
    conn.close()

    print(json.dumps({
        "records": len(dpgs),
        "new": new_count,
        "matched": matched_dpgs,
        "errors": []
    }))


if __name__ == "__main__":
    main()
