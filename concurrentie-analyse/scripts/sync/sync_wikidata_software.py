#!/usr/bin/env python3
"""Pull software taxonomy from Wikidata SPARQL — software types, subclasses, and instances."""

import json
import sqlite3
import sys
import urllib.request
import urllib.parse

DB_PATH = sys.argv[1] if len(sys.argv) > 1 else "concurrentie-analyse/intelligence.db"

# SPARQL query: get all software types that are subclasses of "application software" (Q166142)
# with their labels, descriptions, and instance counts
SPARQL = """
SELECT ?item ?itemLabel ?itemDescription ?parentLabel (COUNT(?instance) AS ?instanceCount) WHERE {
  ?item wdt:P279* wd:Q166142 .    # subclass of application software
  ?item wdt:P279 ?parent .         # direct parent
  OPTIONAL { ?instance wdt:P31 ?item }
  SERVICE wikibase:label { bd:serviceParam wikibase:language "en,nl" }
}
GROUP BY ?item ?itemLabel ?itemDescription ?parentLabel
HAVING (COUNT(?instance) > 2)
ORDER BY DESC(?instanceCount)
LIMIT 500
"""

ENDPOINT = "https://query.wikidata.org/sparql"


def fetch_wikidata():
    """Execute SPARQL query and return results."""
    params = urllib.parse.urlencode({"query": SPARQL, "format": "json"})
    url = f"{ENDPOINT}?{params}"
    req = urllib.request.Request(url, headers={"User-Agent": "ConductionIntelligence/1.0"})
    with urllib.request.urlopen(req, timeout=60) as resp:
        return json.loads(resp.read().decode())


def main():
    data = fetch_wikidata()
    results = data.get("results", {}).get("bindings", [])

    conn = sqlite3.connect(DB_PATH)

    new_count = 0
    updated = 0

    for row in results:
        wikidata_id = row["item"]["value"].split("/")[-1]
        label = row.get("itemLabel", {}).get("value", "")
        description = row.get("itemDescription", {}).get("value", "")
        parent = row.get("parentLabel", {}).get("value", "")
        instance_count = int(row.get("instanceCount", {}).get("value", "0"))

        if not label or label == wikidata_id:
            continue

        wikidata_url = f"https://www.wikidata.org/wiki/{wikidata_id}"

        # Try to match to our software_categories
        slug_guess = label.lower().replace(" ", "-").replace("/", "-")[:50]
        match = conn.execute(
            "SELECT slug FROM software_categories WHERE name_en LIKE ? OR slug LIKE ?",
            (f"%{label}%", f"%{slug_guess}%")
        ).fetchone()

        # Store as a feature source if we can match a category
        if match:
            cat_slug = match[0]
            features = conn.execute(
                "SELECT feature_slug FROM category_features WHERE category_slug = ?",
                (cat_slug,)
            ).fetchall()

            for (feat_slug,) in features:
                cur = conn.execute("""
                    INSERT OR IGNORE INTO feature_sources
                    (category_slug, feature_slug, source_type, source_url, source_label, source_detail)
                    VALUES (?, ?, 'standard', ?, ?, ?)
                """, (cat_slug, feat_slug, wikidata_url,
                      f"Wikidata: {label}", f"{description} ({instance_count} instances, parent: {parent})"))
                if cur.rowcount > 0:
                    new_count += 1
                else:
                    updated += 1

    conn.commit()
    total = len(results)
    conn.close()

    print(json.dumps({"records": total, "new": new_count, "updated": updated, "errors": []}))


if __name__ == "__main__":
    main()
