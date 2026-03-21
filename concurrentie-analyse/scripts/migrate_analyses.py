#!/usr/bin/env python3
"""Migrate ANALYSE.md files into SQLite requirements/integrations tables."""

import os
import re
import sqlite3

SCRIPT_DIR = os.path.dirname(__file__)
DB_PATH = os.path.join(os.path.dirname(SCRIPT_DIR), "intelligence.db")
DOCS_DIR = os.path.join(os.path.dirname(SCRIPT_DIR), "tenders", "docs")

# Regex patterns for parsing ANALYSE.md
RE_METADATA_ROW = re.compile(
    r'\|\s*\*\*(.+?)\*\*\s*\|\s*(.+?)\s*\|'
)
RE_EIS = re.compile(
    r'-\s*\*\*([A-Za-z0-9_.\-]+)\*\*\s*(?:\([^)]*\))?\s*:?\s*>?\s*["""](.+?)["""]',
    re.DOTALL,
)
RE_WENS = re.compile(
    r'-\s*\*\*([A-Za-z0-9_.\-]+)\*\*\s*(?:\(([^)]*)\))?\s*:?\s*>?\s*["""](.+?)["""]',
    re.DOTALL,
)
RE_SECTION = re.compile(r'^#{2,3}\s+(.+)$', re.MULTILINE)
RE_TOTAAL_EISEN = re.compile(r'\*\*Totaal eisen:\s*(\d+)\*\*')
RE_TOTAAL_WENSEN = re.compile(r'\*\*Totaal wensen:\s*(\d+)\*\*')
RE_WINNER = re.compile(r'(?:winnaar|gegund aan|opdrachtnemer)[:\s]*\**([^*|\n]+)', re.IGNORECASE)
RE_VALUE = re.compile(r'EUR\s*([\d.,]+)', re.IGNORECASE)
RE_BIDS = re.compile(r'(\d+)\s*(?:inschrijvingen|inschrijvers|biedingen)', re.IGNORECASE)

# Integration table pattern
RE_INTEGRATIE_ROW = re.compile(
    r'\|\s*[A-Z]*-?\d*\s*\|\s*([^|]+)\|\s*([^|]+)\|\s*([^|]*)\|\s*([^|]*)\|'
)

# Category name mapping for spec_layer classification
CATEGORY_TO_LAYER = {
    "zaakgericht werken": "procest",
    "case management": "procest",
    "zaaktypeconfiguratie": "procest",
    "zaakregistratie": "procest",
    "zaakbehandeling": "procest",
    "werkvoorraad": "procest",
    "vth": "procest",
    "vergunningen": "procest",
    "toezicht": "procest",
    "handhaving": "procest",
    "document management": "docudesk",
    "documentcreatie": "docudesk",
    "documentbeheer": "docudesk",
    "archivering": "openregister",
    "vernietiging": "openregister",
    "recordmanagement": "openregister",
    "formulieren": "procest",
    "intake": "procest",
    "workflow": "procest",
    "procesautomatisering": "procest",
    "bpmn": "procest",
    "zoeken": "openregister",
    "filteren": "openregister",
    "rapportage": "mydash",
    "dashboard": "mydash",
    "klantinteractie": "pipelinq",
    "crm": "pipelinq",
    "contactmanagement": "pipelinq",
    "communicatie": "nextcloud",
    "notificaties": "nextcloud",
    "gebruikersbeheer": "nextcloud",
    "autorisatie": "openregister",
    "geografie": "openregister",
    "gis": "openregister",
    "integratie": "openconnector",
    "koppeling": "openconnector",
    "api": "openregister",
    "beveiliging": "nextcloud",
    "privacy": "openregister",
    "avg": "openregister",
    "gdpr": "openregister",
    "architectuur": "nextcloud",
    "hosting": "nextcloud",
    "sla": "nextcloud",
    "beheer": "nextcloud",
}


def classify_layer(category_name):
    """Map a category heading to a spec layer."""
    lower = category_name.lower()
    for key, layer in CATEGORY_TO_LAYER.items():
        if key in lower:
            return layer
    return None


def parse_metadata(text):
    """Extract metadata from the table at the top."""
    meta = {}
    for match in RE_METADATA_ROW.finditer(text):
        key = match.group(1).strip()
        val = match.group(2).strip()
        meta[key] = val
    return meta


def extract_section_content(text, section_name, next_sections=None):
    """Get text content between a section heading and the next heading."""
    pattern = re.compile(
        r'^#{2,3}\s+' + re.escape(section_name) + r'\s*$',
        re.MULTILINE | re.IGNORECASE
    )
    match = pattern.search(text)
    if not match:
        return ""
    start = match.end()
    # Find next section heading
    next_heading = re.search(r'^#{2,3}\s+', text[start:], re.MULTILINE)
    if next_heading:
        return text[start:start + next_heading.start()]
    return text[start:]


def parse_requirements(text, section_start, section_end_marker, req_type):
    """Extract requirements from a section of the ANALYSE.md."""
    requirements = []
    current_category = "Overig"

    # Split into lines and process
    lines = text.split("\n")
    in_section = False
    past_section = False

    for line in lines:
        # Track section headings
        heading_match = RE_SECTION.match(line)
        if heading_match:
            heading = heading_match.group(1).strip()
            if section_start.lower() in heading.lower():
                in_section = True
                continue
            if in_section and section_end_marker and section_end_marker.lower() in heading.lower():
                past_section = True
                break
            if in_section:
                # Sub-heading = category
                current_category = heading

        if not in_section or past_section:
            continue

        # Try to match requirement lines
        if req_type == "eis":
            match = RE_EIS.search(line)
        else:
            match = RE_WENS.search(line)

        if match:
            if req_type == "wens":
                code = match.group(1)
                weight_str = match.group(2) or ""
                text_nl = match.group(3).strip()
                # Try to parse weight
                weight = None
                weight_nums = re.findall(r'\d+', weight_str)
                if weight_nums:
                    weight = int(weight_nums[0])
            else:
                code = match.group(1)
                text_nl = match.group(2).strip()
                weight = None

            requirements.append({
                "code": code,
                "category": current_category,
                "text_nl": text_nl,
                "type": req_type,
                "weight": weight,
                "spec_layer": classify_layer(current_category),
            })

    return requirements


def parse_integrations(text):
    """Extract integration requirements from the integration table."""
    integrations = []
    # Find integration section
    section = ""
    for marker in ["Integratie-eisen", "Integratievereisten", "Integratie"]:
        idx = text.lower().find(marker.lower())
        if idx >= 0:
            section = text[idx:idx + 5000]
            break

    if not section:
        return integrations

    for match in RE_INTEGRATIE_ROW.finditer(section):
        system = match.group(1).strip()
        direction = match.group(2).strip().lower()
        protocol = match.group(3).strip()
        details = match.group(4).strip() if match.lastindex >= 4 else ""

        # Skip header rows
        if system.lower() in ("systeem", "---", "system", "#"):
            continue
        if not system or system.startswith("---"):
            continue

        # Normalize direction
        if direction in ("in", "inkomend", "inbound"):
            direction = "in"
        elif direction in ("out", "uitgaand", "outbound"):
            direction = "out"
        elif direction in ("bi", "bidirectioneel", "bidirectional", "twee"):
            direction = "bi"

        integrations.append({
            "system": system,
            "direction": direction,
            "protocol": protocol,
            "details": details,
        })

    return integrations


def process_analyse(filepath, conn):
    """Parse one ANALYSE.md and insert into database."""
    with open(filepath, "r", encoding="utf-8") as f:
        text = f.read()

    # Extract metadata
    meta = parse_metadata(text)
    tender_id_str = meta.get("TenderNed ID", "").strip()
    if not tender_id_str:
        return None

    # Find tender in DB
    row = conn.execute(
        "SELECT id FROM tenders WHERE publicatie_id = ?",
        (tender_id_str,)
    ).fetchone()
    if not row:
        return None
    tender_id = row[0]

    # Check if already migrated
    existing = conn.execute(
        "SELECT COUNT(*) FROM requirements WHERE tender_id = ?",
        (tender_id,)
    ).fetchone()[0]
    if existing > 0:
        return {"tender_id": tender_id, "eisen": 0, "wensen": 0, "integrations": 0, "skipped": True}

    # Extract winner and value from text
    winner_match = RE_WINNER.search(text)
    value_match = RE_VALUE.search(text)
    bids_match = RE_BIDS.search(text)

    if winner_match:
        winner = winner_match.group(1).strip().rstrip(".,;")
        conn.execute("UPDATE tenders SET winner = ? WHERE id = ? AND winner IS NULL",
                     (winner, tender_id))
    if value_match:
        value_str = value_match.group(1).replace(".", "").replace(",", ".")
        try:
            value = float(value_str)
            conn.execute("UPDATE tenders SET contract_value = ? WHERE id = ? AND contract_value IS NULL",
                         (value, tender_id))
        except ValueError:
            pass
    if bids_match:
        conn.execute("UPDATE tenders SET bid_count = ? WHERE id = ? AND bid_count IS NULL",
                     (int(bids_match.group(1)), tender_id))

    # Parse eisen
    eisen = parse_requirements(text, "Functionele eisen", "Wensen", "eis")

    # Parse wensen
    wensen = parse_requirements(text, "Wensen", "Nota van Inlichtingen", "wens")
    if not wensen:
        wensen = parse_requirements(text, "Wensen", "Integratie", "wens")

    # Insert requirements
    for req in eisen + wensen:
        conn.execute("""INSERT INTO requirements
            (tender_id, code, category, text_nl, type, weight, spec_layer)
            VALUES (?, ?, ?, ?, ?, ?, ?)""",
            (tender_id, req["code"], req["category"], req["text_nl"],
             req["type"], req["weight"], req["spec_layer"]))

    # Update counts
    conn.execute("UPDATE tenders SET eisen_count = ?, wensen_count = ? WHERE id = ?",
                 (len(eisen), len(wensen), tender_id))

    # Parse integrations
    integrations = parse_integrations(text)
    for integ in integrations:
        conn.execute("""INSERT INTO integrations
            (tender_id, system, direction, protocol, details)
            VALUES (?, ?, ?, ?, ?)""",
            (tender_id, integ["system"], integ["direction"],
             integ["protocol"], integ["details"]))

    return {
        "tender_id": tender_id,
        "eisen": len(eisen),
        "wensen": len(wensen),
        "integrations": len(integrations),
        "skipped": False,
    }


def main():
    if not os.path.exists(DB_PATH):
        print(f"Database not found: {DB_PATH}")
        return

    conn = sqlite3.connect(DB_PATH)
    conn.execute("PRAGMA foreign_keys=ON")

    # Find all ANALYSE.md files
    analyses = []
    for folder in sorted(os.listdir(DOCS_DIR)):
        analyse_path = os.path.join(DOCS_DIR, folder, "ANALYSE.md")
        if os.path.exists(analyse_path):
            analyses.append(analyse_path)

    print(f"Found {len(analyses)} ANALYSE.md files")

    total_eisen = 0
    total_wensen = 0
    total_integ = 0
    processed = 0
    skipped = 0
    failed = 0

    for path in analyses:
        folder = os.path.basename(os.path.dirname(path))
        try:
            result = process_analyse(path, conn)
            if result is None:
                print(f"  SKIP (no match): {folder}")
                failed += 1
                continue
            if result.get("skipped"):
                skipped += 1
                continue
            total_eisen += result["eisen"]
            total_wensen += result["wensen"]
            total_integ += result["integrations"]
            processed += 1
            if result["eisen"] == 0 and result["wensen"] == 0:
                print(f"  WARN (0 requirements): {folder}")
        except Exception as e:
            print(f"  ERROR: {folder}: {e}")
            failed += 1

    conn.commit()

    print(f"\nResults:")
    print(f"  Processed: {processed}")
    print(f"  Skipped (already migrated): {skipped}")
    print(f"  Failed: {failed}")
    print(f"  Total eisen: {total_eisen}")
    print(f"  Total wensen: {total_wensen}")
    print(f"  Total integrations: {total_integ}")

    conn.close()


if __name__ == "__main__":
    main()
