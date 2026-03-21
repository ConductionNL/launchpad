#!/usr/bin/env python3
"""Import TEC RFP Template Excel files into the intelligence database.

Processes all .xlsx files in concurrentie-analyse/RFP-templates/.
Tracks which files have been processed in the tec_imports table to avoid re-processing.
Drop new files in the folder and re-run — only new files will be processed.

Usage:
    python3 sync_tec_rfp.py [db_path]
"""

import hashlib
import json
import os
import re
import sqlite3
import sys

DB_PATH = sys.argv[1] if len(sys.argv) > 1 else "concurrentie-analyse/intelligence.db"
RFP_DIR = os.path.join(os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))), "RFP-templates")

# Map TEC template names to our software categories
TEC_CATEGORY_MAP = {
    "customer relationship management": "crm",
    "crm": "crm",
    "sales force automation": "crm",
    "document management": "dms",
    "web content management": "website-cms",
    "content management": "website-cms",
    "generic erp": "erp",
    "core erp": "erp",
    "erp for municipalities": "erp",
    "erp for services": "erp",
    "erp for distribution": "erp",
    "erp for school districts": "erp",
    "erp fashion": "erp",
    "small business software": "erp",
    "discrete manufacturing": "erp",
    "process manufacturing": "erp",
    "mixed mode manufacturing": "erp",
    "engineer-to-order": "erp",
    "accounting": "boekhouding",
    "financial": "boekhouding",
    "human capital management": "hrm",
    "human resources": "hrm",
    "core hr": "hrm",
    "recruitment": "hrm",
    "talent management": "hrm",
    "talent acquisition": "hrm",
    "incentive and compensation": "hrm",
    "learning management": "hrm",
    "business intelligence": "bi-reporting",
    "business process management": "zaaksysteem",
    "bpm": "zaaksysteem",
    "ppm": "projectmanagement",
    "project": "projectmanagement",
    "field service management": "meldingen",
    "cmms": "inkoop",
    "eam": "inkoop",
    "asset management": "inkoop",
    "warehouse management": "inkoop",
    "mining": "erp",
    "oil and gas": "erp",
    "mill-based": "erp",
}


def get_file_hash(filepath):
    """Get SHA256 hash of file for dedup."""
    h = hashlib.sha256()
    with open(filepath, 'rb') as f:
        for chunk in iter(lambda: f.read(8192), b''):
            h.update(chunk)
    return h.hexdigest()


def classify_template(filename):
    """Map a TEC template filename to our software category."""
    name_lower = filename.lower()
    for keyword, slug in TEC_CATEGORY_MAP.items():
        if keyword in name_lower:
            return slug
    return None


def parse_excel(filepath):
    """Parse a TEC RFP template Excel file, extracting the feature hierarchy."""
    try:
        import openpyxl
    except ImportError:
        print("ERROR: openpyxl required. Install: pip install openpyxl", file=sys.stderr)
        return []

    wb = openpyxl.load_workbook(filepath, data_only=True)
    ws = wb.active

    features = []
    header_row = None

    for i, row in enumerate(ws.iter_rows(values_only=True), 1):
        if not row or not row[0]:
            continue

        col_a = str(row[0]).strip() if row[0] else ""
        # Name can be in col B (index 1) or col C (index 2) depending on template
        col_b = str(row[1]).strip() if len(row) > 1 and row[1] else ""
        col_c = str(row[2]).strip() if len(row) > 2 and row[2] else ""
        col_d = str(row[3]).strip() if len(row) > 3 and row[3] else ""

        # Skip header/meta rows
        if not col_a or col_a in ('Hierarchy', 'Priority', 'About This Sample', 'BUY FULL TEMPLATE',
                      'BUY THE FULL PRODUCT', 'CONTACT US', 'USER RESPONSES', 'VENDOR RESPONSES'):
            continue
        if col_a.startswith('This sample') or col_a.startswith('Welcome to'):
            continue
        if 'criterion' in col_a.lower() and 'contains' in col_a.lower():
            continue

        # Check if this is a numbered hierarchy entry
        hierarchy_match = re.match(r'^(\d+(?:\.\d+)*)\s*$', col_a)
        if hierarchy_match:
            code = hierarchy_match.group(1)
            # Name is in first non-empty column after the code (usually C, sometimes B)
            name = col_c if col_c else col_b
            description = col_d if col_c else col_c

            if not name:
                continue

            # Determine depth/level from hierarchy code
            level = code.count('.') + 1

            features.append({
                'code': code,
                'name': name[:200],
                'description': description[:500] if description else '',
                'level': level,
                'is_category': level <= 2,  # Level 1-2 are categories, 3+ are features
            })

    wb.close()
    return features


def ensure_tracking_table(conn):
    """Create the tec_imports tracking table if it doesn't exist."""
    conn.execute("""
        CREATE TABLE IF NOT EXISTS tec_imports (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            filename TEXT UNIQUE NOT NULL,
            file_hash TEXT NOT NULL,
            category_slug TEXT,
            features_extracted INTEGER DEFAULT 0,
            sources_created INTEGER DEFAULT 0,
            processed_at TEXT DEFAULT CURRENT_TIMESTAMP
        )
    """)
    conn.commit()


def main():
    if not os.path.isdir(RFP_DIR):
        print(json.dumps({"records": 0, "new": 0, "errors": [f"RFP directory not found: {RFP_DIR}"]}))
        return

    conn = sqlite3.connect(DB_PATH)
    ensure_tracking_table(conn)

    # Get already processed files
    processed = set()
    for row in conn.execute("SELECT filename, file_hash FROM tec_imports"):
        processed.add((row[0], row[1]))

    xlsx_files = [f for f in os.listdir(RFP_DIR) if f.endswith('.xlsx')]

    total_features = 0
    new_sources = 0
    files_processed = 0
    skipped = 0
    errors = []

    for filename in sorted(xlsx_files):
        filepath = os.path.join(RFP_DIR, filename)
        file_hash = get_file_hash(filepath)

        # Skip if already processed
        if (filename, file_hash) in processed:
            skipped += 1
            continue

        # Also skip if same filename processed (even with different hash — redownload)
        existing = conn.execute("SELECT id FROM tec_imports WHERE filename = ?", (filename,)).fetchone()
        if existing:
            # Update hash, skip processing
            conn.execute("UPDATE tec_imports SET file_hash = ? WHERE filename = ?", (file_hash, filename))
            conn.commit()
            skipped += 1
            continue

        category_slug = classify_template(filename)
        if not category_slug:
            errors.append(f"Could not classify: {filename}")
            continue

        try:
            features = parse_excel(filepath)
        except Exception as e:
            errors.append(f"Error parsing {filename}: {str(e)[:100]}")
            continue

        if not features:
            errors.append(f"No features found in: {filename}")
            continue

        total_features += len(features)
        files_processed += 1

        # Get our features for this category
        our_features = conn.execute(
            "SELECT feature_slug FROM category_features WHERE category_slug = ?",
            (category_slug,)
        ).fetchall()

        file_sources = 0

        # Create feature sources — each TEC feature becomes a source for our category features
        for tec_feat in features:
            if tec_feat['is_category']:
                # Level 1-2 are module/category headers — still useful as sources
                source_label = f"TEC RFP: {tec_feat['name']} (module)"
            else:
                source_label = f"TEC RFP: {tec_feat['name']}"

            source_detail = f"Code: {tec_feat['code']}. {tec_feat['description']}" if tec_feat['description'] else f"Code: {tec_feat['code']}"

            for (feat_slug,) in our_features:
                cur = conn.execute("""
                    INSERT OR IGNORE INTO feature_sources
                    (category_slug, feature_slug, source_type, source_url, source_label, source_detail)
                    VALUES (?, ?, 'tec', ?, ?, ?)
                """, (category_slug, feat_slug,
                      f"https://www3.technologyevaluation.com/selection-tools/p/rfp-templates",
                      source_label, source_detail[:500]))
                if cur.rowcount > 0:
                    new_sources += 1
                    file_sources += 1

        # Record this file as processed
        conn.execute("""
            INSERT INTO tec_imports (filename, file_hash, category_slug, features_extracted, sources_created)
            VALUES (?, ?, ?, ?, ?)
        """, (filename, file_hash, category_slug, len(features), file_sources))

        conn.commit()

    conn.commit()
    conn.close()

    print(json.dumps({
        "records": total_features,
        "new": new_sources,
        "files_processed": files_processed,
        "files_skipped": skipped,
        "errors": errors
    }))


if __name__ == "__main__":
    main()
