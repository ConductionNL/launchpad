#!/usr/bin/env python3
"""Pull authorized cloud products from FedRAMP Marketplace."""

import json
import re
import sqlite3
import sys
import urllib.request

DB_PATH = sys.argv[1] if len(sys.argv) > 1 else "concurrentie-analyse/intelligence.db"

MARKETPLACE_URL = "https://www.fedramp.gov/marketplace/products/"

KEYWORD_MAP = {
    "case management": "zaaksysteem",
    "document management": "dms",
    "content management": "website-cms",
    "crm": "crm",
    "customer relationship": "crm",
    "erp": "erp",
    "enterprise resource": "erp",
    "accounting": "boekhouding",
    "financial": "boekhouding",
    "human resource": "hrm",
    "hr ": "hrm",
    "project management": "projectmanagement",
    "gis": "gis",
    "geographic": "gis",
    "identity": "iam",
    "single sign-on": "iam",
    "access management": "iam",
    "authentication": "iam",
    "business intelligence": "bi-reporting",
    "analytics": "bi-reporting",
    "reporting": "bi-reporting",
    "integration": "integratie",
    "api": "integratie",
    "collaboration": "participatie",
    "email": None,
    "cloud infrastructure": None,
    "iaas": None,
    "paas": None,
}


def fetch_marketplace():
    """Fetch FedRAMP marketplace HTML and extract product data."""
    req = urllib.request.Request(MARKETPLACE_URL, headers={
        "User-Agent": "ConductionIntelligence/1.0"
    })
    try:
        with urllib.request.urlopen(req, timeout=60) as resp:
            return resp.read().decode()
    except Exception as e:
        print(json.dumps({"records": 0, "new": 0, "errors": [f"Failed to fetch: {e}"]}))
        sys.exit(0)


def parse_products(html):
    """Extract product info from FedRAMP marketplace HTML."""
    products = []

    # Look for product cards or structured data
    # FedRAMP uses SvelteKit — data may be in JSON script tags
    json_match = re.search(r'<script[^>]*>.*?type\s*:\s*"product".*?</script>', html, re.DOTALL)

    # Try to find product names and details from the HTML structure
    # Pattern: product cards with names
    card_pattern = re.findall(
        r'(?:product[_-]?name|data-name|aria-label)["\s:=]+([^"<]+)',
        html, re.IGNORECASE
    )

    if card_pattern:
        for name in set(card_pattern):
            name = name.strip()
            if len(name) > 3 and len(name) < 100:
                products.append({"name": name, "url": MARKETPLACE_URL})

    # Also try to extract from any embedded JSON data
    json_blocks = re.findall(r'<script[^>]*type="application/json"[^>]*>(.*?)</script>', html, re.DOTALL)
    for block in json_blocks:
        try:
            data = json.loads(block)
            if isinstance(data, list):
                for item in data:
                    if isinstance(item, dict) and "name" in item:
                        products.append({
                            "name": item["name"],
                            "description": item.get("description", ""),
                            "url": item.get("url", MARKETPLACE_URL),
                            "status": item.get("status", item.get("authorization_status", ""))
                        })
            elif isinstance(data, dict):
                # Nested data structures
                for key in ["products", "data", "items", "results"]:
                    if key in data and isinstance(data[key], list):
                        for item in data[key]:
                            if isinstance(item, dict) and "name" in item:
                                products.append({
                                    "name": item["name"],
                                    "description": item.get("description", ""),
                                    "url": MARKETPLACE_URL,
                                })
        except json.JSONDecodeError:
            continue

    return products


def classify(name, desc=""):
    """Match product to our categories."""
    text = f"{name} {desc}".lower()
    matches = set()
    for kw, slug in KEYWORD_MAP.items():
        if slug and kw in text:
            matches.add(slug)
    return list(matches)


def main():
    html = fetch_marketplace()
    products = parse_products(html)

    conn = sqlite3.connect(DB_PATH)
    new_count = 0
    matched = 0

    for product in products:
        name = product.get("name", "")
        desc = product.get("description", "")
        url = product.get("url", MARKETPLACE_URL)

        categories = classify(name, desc)
        if not categories:
            continue

        matched += 1

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
                """, (cat_slug, feat_slug, url,
                      f"FedRAMP: {name}",
                      f"US government authorized cloud service. {desc[:150]}"))
                if cur.rowcount > 0:
                    new_count += 1

    conn.commit()
    conn.close()

    print(json.dumps({
        "records": len(products),
        "new": new_count,
        "matched": matched,
        "errors": []
    }))


if __name__ == "__main__":
    main()
