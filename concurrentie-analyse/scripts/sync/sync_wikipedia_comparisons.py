#!/usr/bin/env python3
"""Pull feature comparison tables from Wikipedia 'Comparison of...' articles."""

import json
import re
import sqlite3
import sys
import urllib.request
import urllib.parse
from html.parser import HTMLParser

DB_PATH = sys.argv[1] if len(sys.argv) > 1 else "concurrentie-analyse/intelligence.db"

WIKI_API = "https://en.wikipedia.org/w/api.php"

# Wikipedia comparison articles mapped to our categories
COMPARISON_PAGES = {
    "crm": [
        "Comparison_of_CRM_systems",
    ],
    "dms": [
        "Comparison_of_document-management_systems",
    ],
    "erp": [
        "Comparison_of_accounting_software",
    ],
    "boekhouding": [
        "Comparison_of_accounting_software",
    ],
    "projectmanagement": [
        "Comparison_of_project_management_software",
    ],
    "website-cms": [
        "Comparison_of_content_management_systems",
    ],
    "formulieren": [
        "Comparison_of_survey_software",
    ],
    "bi-reporting": [
        "Comparison_of_business_intelligence_tools",
    ],
    "gis": [
        "Comparison_of_geographic_information_systems_software",
    ],
    "hrm": [
        "Comparison_of_human_resource_management_software",
    ],
}


class TableParser(HTMLParser):
    """Extract feature column headers from HTML tables."""

    def __init__(self):
        super().__init__()
        self.in_table = False
        self.in_th = False
        self.headers = []
        self.current_text = ""
        self.tables = []  # list of header lists

    def handle_starttag(self, tag, attrs):
        if tag == "table":
            self.in_table = True
            self.headers = []
        elif tag == "th" and self.in_table:
            self.in_th = True
            self.current_text = ""

    def handle_endtag(self, tag):
        if tag == "th" and self.in_th:
            self.in_th = False
            text = self.current_text.strip()
            if text:
                self.headers.append(text)
        elif tag == "table" and self.in_table:
            self.in_table = False
            if len(self.headers) > 2:
                self.tables.append(self.headers)

    def handle_data(self, data):
        if self.in_th:
            self.current_text += data


def fetch_page_html(title):
    """Fetch the HTML content of a Wikipedia page."""
    params = urllib.parse.urlencode({
        "action": "parse",
        "page": title,
        "format": "json",
        "prop": "text",
    })
    url = f"{WIKI_API}?{params}"
    req = urllib.request.Request(url, headers={"User-Agent": "ConductionIntelligence/1.0"})
    with urllib.request.urlopen(req, timeout=60) as resp:
        data = json.loads(resp.read().decode())
    return data.get("parse", {}).get("text", {}).get("*", "")


def extract_features_from_headers(headers):
    """Filter table headers to likely feature names."""
    skip = {"Name", "Software", "Creator", "Developer", "License", "Cost",
            "Operating system", "Platform", "Language", "First release",
            "Latest release", "Latest stable release", "Written in",
            "Website", "Pricing", "Type", "Status", "Notes", "Source model",
            "Initial release", "Stable release"}
    features = []
    for h in headers:
        clean = re.sub(r"\[.*?\]", "", h).strip()
        if clean and clean not in skip and len(clean) > 1 and len(clean) < 60:
            features.append(clean)
    return features


def main():
    conn = sqlite3.connect(DB_PATH)
    total_features = 0
    new_count = 0
    errors = []

    for cat_slug, pages in COMPARISON_PAGES.items():
        our_features = conn.execute(
            "SELECT feature_slug FROM category_features WHERE category_slug = ?",
            (cat_slug,)
        ).fetchall()

        for page_title in pages:
            try:
                html = fetch_page_html(page_title)
                parser = TableParser()
                parser.feed(html)

                wiki_url = f"https://en.wikipedia.org/wiki/{page_title}"

                for table_headers in parser.tables:
                    features = extract_features_from_headers(table_headers)
                    total_features += len(features)

                    # Create sources linking Wikipedia features to our category
                    for wiki_feature in features:
                        for (feat_slug,) in our_features:
                            cur = conn.execute("""
                                INSERT OR IGNORE INTO feature_sources
                                (category_slug, feature_slug, source_type, source_url,
                                 source_label, source_detail)
                                VALUES (?, ?, 'standard', ?, ?, ?)
                            """, (cat_slug, feat_slug, wiki_url,
                                  f"Wikipedia: {page_title.replace('_', ' ')}",
                                  f"Comparison feature: {wiki_feature}"))
                            if cur.rowcount > 0:
                                new_count += 1

            except Exception as e:
                errors.append(f"{page_title}: {str(e)}")

    conn.commit()
    conn.close()

    print(json.dumps({
        "records": total_features,
        "new": new_count,
        "pages_processed": sum(len(v) for v in COMPARISON_PAGES.values()),
        "errors": errors
    }))


if __name__ == "__main__":
    main()
