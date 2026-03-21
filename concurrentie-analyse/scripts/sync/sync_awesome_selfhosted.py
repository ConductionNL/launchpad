#!/usr/bin/env python3
"""Pull software categories and projects from awesome-selfhosted GitHub README."""

import json
import re
import sqlite3
import sys
import urllib.request
import base64

DB_PATH = sys.argv[1] if len(sys.argv) > 1 else "concurrentie-analyse/intelligence.db"

REPO_API = "https://api.github.com/repos/awesome-selfhosted/awesome-selfhosted/contents/README.md"


def fetch_readme():
    """Fetch and decode the awesome-selfhosted README."""
    req = urllib.request.Request(REPO_API, headers={
        "User-Agent": "ConductionIntelligence/1.0",
        "Accept": "application/vnd.github.v3+json"
    })
    with urllib.request.urlopen(req, timeout=60) as resp:
        data = json.loads(resp.read().decode())
    return base64.b64decode(data["content"]).decode("utf-8")


def parse_categories(readme):
    """Parse categories and their software entries from README markdown."""
    categories = {}
    current_cat = None
    lines = readme.split("\n")

    for line in lines:
        # Category headers: ## Category Name
        cat_match = re.match(r"^### (.+?)(?:\s*$)", line)
        if cat_match:
            current_cat = cat_match.group(1).strip()
            if current_cat not in ("Contents", "List of Licenses", "External Links",
                                    "Anti-features", "Contributing", "License"):
                categories[current_cat] = []
            else:
                current_cat = None
            continue

        # Software entries: - [Name](url) - Description. `License` `Language`
        if current_cat and line.strip().startswith("- ["):
            entry_match = re.match(
                r"^- \[([^\]]+)\]\(([^)]+)\)\s*(?:`[^`]*`\s*)?-\s*(.+?)(?:\s*\(`([^`]+)`\s*`([^`]+)`\))?$",
                line.strip()
            )
            if not entry_match:
                # Simpler pattern
                entry_match = re.match(r"^- \[([^\]]+)\]\(([^)]+)\)\s*-\s*(.+)", line.strip())
                if entry_match:
                    categories[current_cat].append({
                        "name": entry_match.group(1),
                        "url": entry_match.group(2),
                        "description": entry_match.group(3).strip(),
                    })
            else:
                categories[current_cat].append({
                    "name": entry_match.group(1),
                    "url": entry_match.group(2),
                    "description": entry_match.group(3).strip(),
                    "license": entry_match.group(4) if entry_match.lastindex >= 4 else None,
                    "language": entry_match.group(5) if entry_match.lastindex >= 5 else None,
                })

    return categories


# Map awesome-selfhosted categories to our software_categories slugs
CATEGORY_MAP = {
    "Archiving and Digital Preservation": "archivering",
    "Automation": "integratie",
    "Bookmarks and Link Sharing": None,
    "Calendar & Contacts": "planning",
    "Communication - Custom Communication Systems": None,
    "Communication - Email": None,
    "Communication - Social Networks and Forums": "participatie",
    "Community-Supported Agriculture (CSA)": None,
    "Conference Management": None,
    "Content Management Systems (CMS)": "website-cms",
    "Customer Relationship Management (CRM)": "crm",
    "Database Management": "objectregistratie",
    "Document Management": "dms",
    "Document Management - E-books": None,
    "Document Management - Institutional Repository and Digital Library Software": "archivering",
    "E-commerce": None,
    "Enterprise Resource Planning": "erp",
    "File Transfer & Synchronization": None,
    "Forms & Surveys": "formulieren",
    "Genealogy": None,
    "Groupware": None,
    "Human Resources Management (HRM)": "hrm",
    "Internet of Things (IoT)": None,
    "Inventory Management": "inkoop",
    "Knowledge Management Tools": None,
    "Learning and Courses": None,
    "Maps and Global Positioning System (GPS)": "gis",
    "Media Streaming": None,
    "Miscellaneous": None,
    "Money, Budgeting & Management": "boekhouding",
    "Monitoring": None,
    "Note-taking & Editors": None,
    "Office Suites": None,
    "Password Managers": "iam",
    "Pastebins": None,
    "Personal Dashboards": "dashboard",
    "Photo and Video Galleries": None,
    "Polls and Events": "participatie",
    "Project Management": "projectmanagement",
    "Proxy": None,
    "Recipe Management": None,
    "Remote Access": None,
    "Resource Planning": "planning",
    "Search Engines": None,
    "Self-hosting Solutions": None,
    "Software Development": None,
    "Status / Uptime pages": None,
    "Task Management & To-Do Lists": "projectmanagement",
    "Ticketing": "meldingen",
    "Time Tracking": "hrm",
    "URL Shorteners": None,
    "VPN": None,
    "Web Servers": None,
    "Wikis": None,
}


def main():
    readme = fetch_readme()
    categories = parse_categories(readme)

    conn = sqlite3.connect(DB_PATH)
    new_count = 0
    total_entries = 0

    for cat_name, entries in categories.items():
        total_entries += len(entries)
        our_slug = CATEGORY_MAP.get(cat_name)
        if not our_slug:
            continue

        # For each entry, create a feature source linking to the project
        features = conn.execute(
            "SELECT feature_slug FROM category_features WHERE category_slug = ?",
            (our_slug,)
        ).fetchall()

        for entry in entries:
            source_url = entry["url"]
            source_label = f"awesome-selfhosted: {entry['name']}"
            detail = entry.get("description", "")[:200]

            for (feat_slug,) in features:
                cur = conn.execute("""
                    INSERT OR IGNORE INTO feature_sources
                    (category_slug, feature_slug, source_type, source_url, source_label, source_detail)
                    VALUES (?, ?, 'awesome-list', ?, ?, ?)
                """, (our_slug, feat_slug, source_url, source_label, detail))
                if cur.rowcount > 0:
                    new_count += 1

    conn.commit()
    conn.close()

    print(json.dumps({
        "records": total_entries,
        "new": new_count,
        "categories": len(categories),
        "mapped_categories": sum(1 for v in CATEGORY_MAP.values() if v),
        "errors": []
    }))


if __name__ == "__main__":
    main()
