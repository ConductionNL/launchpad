#!/usr/bin/env python3
"""Pull software service categories from UK Digital Marketplace (G-Cloud)."""

import json
import re
import sqlite3
import sys
import time
import urllib.request

DB_PATH = sys.argv[1] if len(sys.argv) > 1 else "concurrentie-analyse/intelligence.db"

SEARCH_URL = "https://www.applytosupply.digitalmarketplace.service.gov.uk/g-cloud/search"

# G-Cloud Lot 2 (Cloud Software) categories mapped to our slugs
GCLOUD_CATEGORIES = {
    "accounting-and-finance": ("Accounting and finance", "boekhouding"),
    "analytics-and-business-intelligence": ("Analytics and business intelligence", "bi-reporting"),
    "application-security": ("Application security", "iam"),
    "collaborative-working": ("Collaborative working", None),
    "creative-and-design": ("Creative and design", "ontwerp"),
    "customer-relationship-management-crm": ("Customer relationship management (CRM)", "crm"),
    "electronic-document-and-records-management-edrm": ("Electronic document and records management (EDRM)", "dms"),
    "healthcare": ("Healthcare", None),
    "human-resources-and-employee-management": ("Human resources and employee management", "hrm"),
    "information-and-communication-technology-ict": ("Information and communication technology (ICT)", None),
    "legal": ("Legal", None),
    "marketing": ("Marketing", None),
    "operations-management": ("Operations management", "erp"),
    "project-management-and-planning": ("Project management and planning", "projectmanagement"),
    "sales": ("Sales", "crm"),
    "schools-and-education": ("Schools and education", None),
    "software-development-tools": ("Software development tools", None),
    "transport-and-logistics": ("Transport and logistics", None),
}


def fetch_category_count(category_slug):
    """Fetch the service count for a G-Cloud category."""
    url = f"{SEARCH_URL}?lot=cloud-software&serviceCategories={category_slug}"
    req = urllib.request.Request(url, headers={"User-Agent": "ConductionIntelligence/1.0"})
    try:
        with urllib.request.urlopen(req, timeout=30) as resp:
            html = resp.read().decode()
        # Extract result count from HTML
        match = re.search(r'(\d[\d,]*)\s+results?\s+found', html)
        if match:
            return int(match.group(1).replace(",", ""))
        return 0
    except Exception as e:
        print(f"Error fetching {category_slug}: {e}", file=sys.stderr)
        return 0


def main():
    conn = sqlite3.connect(DB_PATH)

    new_count = 0
    total_services = 0
    matched = 0

    for gcloud_slug, (display_name, our_slug) in GCLOUD_CATEGORIES.items():
        if not our_slug:
            continue

        count = fetch_category_count(gcloud_slug)
        total_services += count

        if count == 0:
            continue

        matched += 1
        gcloud_url = f"{SEARCH_URL}?lot=cloud-software&serviceCategories={gcloud_slug}"

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
                VALUES (?, ?, 'standard', ?, ?, ?)
            """, (our_slug, feat_slug, gcloud_url,
                  f"UK G-Cloud: {display_name}",
                  f"{count} approved cloud services in UK government marketplace"))
            if cur.rowcount > 0:
                new_count += 1

        time.sleep(1)  # Be polite to the UK gov

    conn.commit()
    conn.close()

    print(json.dumps({
        "records": total_services,
        "new": new_count,
        "categories_matched": matched,
        "errors": []
    }))


if __name__ == "__main__":
    main()
