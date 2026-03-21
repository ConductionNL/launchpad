#!/usr/bin/env python3
"""Pull open source solutions from Interoperable Europe Portal via SPARQL."""

import json
import sqlite3
import sys
import urllib.request
import urllib.parse

DB_PATH = sys.argv[1] if len(sys.argv) > 1 else "concurrentie-analyse/intelligence.db"

SPARQL_ENDPOINT = "https://interoperable-europe.ec.europa.eu/sparql"

# Query EU OSS catalogue for software solutions with descriptions and categories
SPARQL_QUERY = """
PREFIX dcat: <http://www.w3.org/ns/dcat#>
PREFIX dct: <http://purl.org/dc/terms/>
PREFIX adms: <http://www.w3.org/ns/adms#>
PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>
PREFIX foaf: <http://xmlns.com/foaf/0.1/>

SELECT DISTINCT ?solution ?title ?description ?landing ?category WHERE {
  ?solution a dcat:Dataset .
  ?solution dct:title ?title .
  OPTIONAL { ?solution dct:description ?description }
  OPTIONAL { ?solution dcat:landingPage ?landing }
  OPTIONAL { ?solution dcat:theme ?category }
  FILTER(LANG(?title) = "en" || LANG(?title) = "")
}
LIMIT 500
"""

# Fallback simpler query if DCAT doesn't work
SPARQL_FALLBACK = """
SELECT DISTINCT ?s ?title ?desc WHERE {
  ?s ?p ?o .
  ?s <http://purl.org/dc/terms/title> ?title .
  OPTIONAL { ?s <http://purl.org/dc/terms/description> ?desc }
  FILTER(LANG(?title) = "en" || LANG(?title) = "")
}
LIMIT 200
"""

KEYWORD_MAP = {
    "case management": "zaaksysteem",
    "document": "dms",
    "content management": "website-cms",
    "crm": "crm",
    "customer": "crm",
    "erp": "erp",
    "accounting": "boekhouding",
    "financial": "boekhouding",
    "hr": "hrm",
    "human resource": "hrm",
    "project": "projectmanagement",
    "gis": "gis",
    "geographic": "gis",
    "spatial": "gis",
    "form": "formulieren",
    "identity": "iam",
    "authentication": "iam",
    "archive": "archivering",
    "reporting": "bi-reporting",
    "analytics": "bi-reporting",
    "integration": "integratie",
    "interoperability": "integratie",
    "catalogue": "catalogus",
    "open data": "catalogus",
    "participation": "participatie",
    "collaboration": "participatie",
}


def fetch_sparql(query):
    """Execute SPARQL query against Interoperable Europe Portal."""
    # This endpoint requires GET with Accept header (POST returns format errors)
    encoded_query = urllib.parse.quote(query)
    url = f"{SPARQL_ENDPOINT}?query={encoded_query}"
    req = urllib.request.Request(url, headers={
        "User-Agent": "ConductionIntelligence/1.0",
        "Accept": "application/sparql-results+json",
    })
    try:
        with urllib.request.urlopen(req, timeout=120) as resp:
            return json.loads(resp.read().decode())
    except Exception as e:
        print(f"SPARQL error: {e}", file=sys.stderr)
        return None


def classify(title, desc=""):
    """Match solution to our categories by keywords."""
    text = f"{title} {desc}".lower()
    matches = set()
    for kw, slug in KEYWORD_MAP.items():
        if kw in text:
            matches.add(slug)
    return list(matches)


def main():
    result = fetch_sparql(SPARQL_QUERY)
    if not result or "results" not in result:
        # Try fallback query
        result = fetch_sparql(SPARQL_FALLBACK)

    if not result or "results" not in result:
        print(json.dumps({"records": 0, "new": 0, "errors": ["SPARQL query returned no results"]}))
        return

    bindings = result["results"]["bindings"]
    conn = sqlite3.connect(DB_PATH)

    new_count = 0
    matched = 0

    for row in bindings:
        title = row.get("title", {}).get("value", "")
        desc = row.get("description", row.get("desc", {})).get("value", "")
        landing = row.get("landing", row.get("s", {})).get("value", "")

        if not title:
            continue

        categories = classify(title, desc)
        if not categories:
            continue

        matched += 1
        source_url = landing or "https://interoperable-europe.ec.europa.eu"

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
                      f"EU Interoperable: {title}",
                      desc[:200] if desc else ""))
                if cur.rowcount > 0:
                    new_count += 1

    conn.commit()
    conn.close()

    print(json.dumps({
        "records": len(bindings),
        "new": new_count,
        "matched": matched,
        "errors": []
    }))


if __name__ == "__main__":
    main()
