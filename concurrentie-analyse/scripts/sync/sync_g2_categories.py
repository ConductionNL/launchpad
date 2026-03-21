#!/usr/bin/env python3
"""Pull software categories and features from G2 API.

Requires G2_API_TOKEN environment variable or reads from CLAUDE.local.md.
"""

import json
import os
import re
import sqlite3
import sys
import time
import urllib.request

DB_PATH = sys.argv[1] if len(sys.argv) > 1 else "concurrentie-analyse/intelligence.db"

API_BASE = "https://data.g2.com/api/v2"
PAGE_SIZE = 100

# Map G2 category names (lowercase) to our software_categories slugs
G2_CATEGORY_MAP = {
    "case management": "zaaksysteem",
    "government": "zaaksysteem",
    "crm": "crm",
    "customer relationship management": "crm",
    "contact management": "crm",
    "customer service": "crm",
    "help desk": "crm",
    "document management": "dms",
    "document generation": "dms",
    "content management": "website-cms",
    "web content management": "website-cms",
    "cms": "website-cms",
    "erp": "erp",
    "enterprise resource planning": "erp",
    "accounting": "boekhouding",
    "bookkeeping": "boekhouding",
    "invoicing": "e-facturatie",
    "hr": "hrm",
    "human resource": "hrm",
    "payroll": "hrm",
    "recruitment": "hrm",
    "applicant tracking": "hrm",
    "project management": "projectmanagement",
    "task management": "projectmanagement",
    "gis": "gis",
    "geographic information": "gis",
    "mapping": "gis",
    "form": "formulieren",
    "survey": "formulieren",
    "identity": "iam",
    "single sign-on": "iam",
    "access management": "iam",
    "archiving": "archivering",
    "records management": "archivering",
    "business intelligence": "bi-reporting",
    "reporting": "bi-reporting",
    "analytics": "bi-reporting",
    "dashboard": "dashboard",
    "integration": "integratie",
    "ipaas": "integratie",
    "api management": "integratie",
    "data integration": "integratie",
    "procurement": "inkoop",
    "purchasing": "inkoop",
    "contract management": "contractbeheer",
    "contract lifecycle": "contractbeheer",
    "citizen engagement": "participatie",
    "grant management": "subsidies",
    "appointment scheduling": "planning",
    "resource scheduling": "planning",
    "it service management": "meldingen",
    "ticketing": "meldingen",
    "issue tracking": "meldingen",
    "catalog": "catalogus",
    "data catalog": "catalogus",
    "design system": "ontwerp",
    "low-code": "objectregistratie",
    "no-code": "objectregistratie",
    "database": "objectregistratie",
}


def get_token():
    """Get G2 API token from env or CLAUDE.local.md."""
    token = os.environ.get("G2_API_TOKEN")
    if token:
        return token

    # Try reading from CLAUDE.local.md
    local_md = os.path.join(os.path.dirname(__file__), "..", "..", "..", ".claude", "CLAUDE.local.md")
    if os.path.exists(local_md):
        with open(local_md) as f:
            for line in f:
                if "Access Token:" in line and "G2" in open(local_md).read():
                    match = re.search(r"Access Token:\s*(\S+)", line)
                    if match:
                        return match.group(1)

    # Direct path fallback
    local_md2 = os.path.join(os.path.dirname(__file__), "..", "..", "..", ".claude", "CLAUDE.local.md")
    try:
        with open(os.path.abspath(local_md2)) as f:
            content = f.read()
        match = re.search(r"G2.*?Access Token:\s*(\S+)", content, re.DOTALL)
        if match:
            return match.group(1)
    except FileNotFoundError:
        pass

    print(json.dumps({"records": 0, "new": 0, "errors": ["G2_API_TOKEN not found. Set env var or add to CLAUDE.local.md"]}))
    sys.exit(0)


def g2_get(path, token):
    """Make an authenticated G2 API request."""
    url = f"{API_BASE}{path}"
    req = urllib.request.Request(url, headers={
        "Authorization": f"Bearer {token}",
        "Accept": "application/json",
        "User-Agent": "ConductionIntelligence/1.0"
    })
    try:
        with urllib.request.urlopen(req, timeout=30) as resp:
            return json.loads(resp.read().decode())
    except Exception as e:
        print(f"G2 API error: {e}", file=sys.stderr)
        return None


def match_category(g2_name):
    """Try to match a G2 category name to our software_categories slug."""
    name_lower = g2_name.lower()
    for keyword, slug in G2_CATEGORY_MAP.items():
        if keyword in name_lower:
            return slug
    return None


def main():
    token = get_token()
    conn = sqlite3.connect(DB_PATH)

    new_count = 0
    total_categories = 0
    matched = 0
    cursor = None  # For pagination

    while True:
        path = f"/categories?page[size]={PAGE_SIZE}"
        if cursor:
            path += f"&page[after]={cursor}"

        data = g2_get(path, token)
        if not data or "data" not in data:
            break

        categories = data["data"]
        if not categories:
            break

        for cat in categories:
            total_categories += 1
            attrs = cat.get("attributes", {})
            g2_name = attrs.get("name", "")
            g2_slug = attrs.get("slug", "")
            g2_desc = attrs.get("description", "")
            g2_uuid = attrs.get("uuid", cat.get("id", ""))

            our_slug = match_category(g2_name)
            if not our_slug:
                continue

            matched += 1
            g2_url = f"https://www.g2.com/categories/{g2_slug}"

            # Get our features for this category
            features = conn.execute(
                "SELECT feature_slug FROM category_features WHERE category_slug = ?",
                (our_slug,)
            ).fetchall()

            for (feat_slug,) in features:
                cur = conn.execute("""
                    INSERT OR IGNORE INTO feature_sources
                    (category_slug, feature_slug, source_type, source_url,
                     source_label, source_detail)
                    VALUES (?, ?, 'g2', ?, ?, ?)
                """, (our_slug, feat_slug, g2_url,
                      f"G2 Category: {g2_name}",
                      g2_desc[:300] if g2_desc else ""))
                if cur.rowcount > 0:
                    new_count += 1

        conn.commit()

        # Pagination
        next_link = data.get("links", {}).get("next")
        if not next_link:
            break

        # Extract cursor from next link
        import urllib.parse
        parsed = urllib.parse.urlparse(next_link)
        params = urllib.parse.parse_qs(parsed.query)
        cursor = params.get("page[after]", [None])[0]
        if not cursor:
            break

        time.sleep(0.5)  # Rate limiting

    conn.commit()
    conn.close()

    print(json.dumps({
        "records": total_categories,
        "new": new_count,
        "matched": matched,
        "errors": []
    }))


if __name__ == "__main__":
    main()
