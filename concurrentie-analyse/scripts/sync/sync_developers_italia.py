#!/usr/bin/env python3
"""Pull government software metadata from Developers Italia (publiccode.yml catalog)."""

import json
import sqlite3
import sys
import urllib.request

DB_PATH = sys.argv[1] if len(sys.argv) > 1 else "concurrentie-analyse/intelligence.db"

API_URL = "https://api.developers.italia.it/v1/software?page=0&size=200"

# Map Italian software categories/tags to ours
CATEGORY_MAP = {
    "document-management": "dms",
    "content-management": "website-cms",
    "crm": "crm",
    "erp": "erp",
    "accounting": "boekhouding",
    "hr": "hrm",
    "human-resources": "hrm",
    "project-management": "projectmanagement",
    "gis": "gis",
    "geographic": "gis",
    "form": "formulieren",
    "identity": "iam",
    "authentication": "iam",
    "archive": "archivering",
    "reporting": "bi-reporting",
    "analytics": "bi-reporting",
    "dashboard": "dashboard",
    "participation": "participatie",
    "procurement": "inkoop",
    "case-management": "zaaksysteem",
    "workflow": "zaaksysteem",
    "integration": "integratie",
    "api-gateway": "integratie",
    "catalogue": "catalogus",
    "open-data": "catalogus",
    "registry": "objectregistratie",
    "database": "objectregistratie",
}


def fetch_catalog():
    """Fetch the full Italian government software catalog."""
    req = urllib.request.Request(API_URL, headers={"User-Agent": "ConductionIntelligence/1.0"})
    with urllib.request.urlopen(req, timeout=120) as resp:
        return json.loads(resp.read().decode())


def classify_software(sw):
    """Match software to our categories using tags and description."""
    tags = [t.lower() for t in sw.get("tags", [])]
    categories_found = set()

    # Check publiccode categories
    for pc_cat in sw.get("categories", []):
        slug = pc_cat.lower().replace(" ", "-")
        if slug in CATEGORY_MAP:
            categories_found.add(CATEGORY_MAP[slug])

    # Check tags
    for tag in tags:
        for keyword, our_slug in CATEGORY_MAP.items():
            if keyword in tag:
                categories_found.add(our_slug)

    # Check description
    desc = (sw.get("description", {}).get("en", {}).get("shortDescription", "") or
            sw.get("description", {}).get("it", {}).get("shortDescription", "") or "").lower()
    for keyword, our_slug in CATEGORY_MAP.items():
        if keyword in desc:
            categories_found.add(our_slug)

    return list(categories_found)


def main():
    catalog = fetch_catalog()

    # The API returns a list of software items
    if isinstance(catalog, dict):
        software_list = catalog.get("data", catalog.get("results", []))
    elif isinstance(catalog, list):
        software_list = catalog
    else:
        print(json.dumps({"records": 0, "new": 0, "errors": ["Unexpected API response format"]}))
        return

    conn = sqlite3.connect(DB_PATH)
    new_count = 0
    matched = 0

    for sw in software_list:
        # Parse publiccodeYml string if present
        pc_raw = sw.get("publiccodeYml", "")
        pc_data = {}
        if pc_raw:
            # Simple YAML-like parsing for key fields (no yaml dependency)
            for line in pc_raw.split("\n"):
                line = line.strip()
                if line.startswith("name:"):
                    pc_data["name"] = line.split(":", 1)[1].strip().strip('"')
                elif line.startswith("shortDescription:"):
                    pc_data["shortDescription"] = line.split(":", 1)[1].strip().strip('"')
                elif line.startswith("- "):
                    pc_data.setdefault("tags", []).append(line[2:].strip())

        name = sw.get("name", pc_data.get("name", ""))
        if not name:
            continue

        url = sw.get("url", "")
        desc = pc_data.get("shortDescription", "")

        # Merge tags from publiccode into sw for classification
        if "tags" not in sw:
            sw["tags"] = pc_data.get("tags", [])

        categories = classify_software(sw)
        if not categories:
            continue

        matched += 1
        source_url = url or f"https://developers.italia.it/en/software/{name.lower().replace(' ', '-')}"

        for cat_slug in categories:
            features = conn.execute(
                "SELECT feature_slug FROM category_features WHERE category_slug = ?",
                (cat_slug,)
            ).fetchall()

            for (feat_slug,) in features:
                cur = conn.execute("""
                    INSERT OR IGNORE INTO feature_sources
                    (category_slug, feature_slug, source_type, source_url,
                     source_label, source_detail)
                    VALUES (?, ?, 'standard', ?, ?, ?)
                """, (cat_slug, feat_slug, source_url,
                      f"Developers Italia: {name}",
                      f"{desc[:200]}"))
                if cur.rowcount > 0:
                    new_count += 1

    conn.commit()
    conn.close()

    print(json.dumps({
        "records": len(software_list),
        "new": new_count,
        "matched": matched,
        "errors": []
    }))


if __name__ == "__main__":
    main()
