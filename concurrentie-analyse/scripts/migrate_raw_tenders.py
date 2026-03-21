#!/usr/bin/env python3
"""Migrate raw TenderNed JSON files into the SQLite database."""

import json
import os
import sqlite3

SCRIPT_DIR = os.path.dirname(__file__)
DB_PATH = os.path.join(os.path.dirname(SCRIPT_DIR), "intelligence.db")
RAW_DIR = os.path.join(os.path.dirname(SCRIPT_DIR), "tenders", "raw")
DOCS_DIR = os.path.join(os.path.dirname(SCRIPT_DIR), "tenders", "docs")


def load_json(path):
    with open(path, "r") as f:
        return json.load(f)


def find_analyse_path(publicatie_id):
    """Check if an ANALYSE.md exists for this tender in the docs folder."""
    for entry in os.listdir(DOCS_DIR):
        if entry.startswith(f"{publicatie_id}-"):
            analyse = os.path.join(DOCS_DIR, entry, "ANALYSE.md")
            if os.path.exists(analyse):
                return os.path.relpath(analyse, os.path.dirname(SCRIPT_DIR))
    return None


def migrate_combined(conn):
    """Import all-tenders-combined.json into tenders table."""
    path = os.path.join(RAW_DIR, "all-tenders-combined.json")
    if not os.path.exists(path):
        print(f"Not found: {path}")
        return 0

    tenders = load_json(path)
    inserted = 0
    updated = 0

    for t in tenders:
        pid = str(t["publicatieId"])
        terms = json.dumps(t.get("_terms", []))
        relevance_list = t.get("_relevance", [])
        relevance = relevance_list[0] if relevance_list else None

        analyse_path = find_analyse_path(pid)
        status = "analysed" if analyse_path else "new"

        tender_url = t.get("link", {}).get("href", "")

        row = conn.execute("SELECT id FROM tenders WHERE publicatie_id = ?", (pid,)).fetchone()
        if row:
            conn.execute("""UPDATE tenders SET
                name = ?, organisation = ?, publication_date = ?, closing_date = ?,
                type_publication_code = ?, type_publication = ?,
                procedure_code = ?, procedure = ?, type_opdracht = ?,
                description = ?, is_european = ?, is_digital = ?,
                relevance = ?, search_terms = ?, tender_url = ?,
                analyse_path = COALESCE(analyse_path, ?),
                status = CASE WHEN status = 'new' THEN ? ELSE status END,
                updated_at = datetime('now')
                WHERE publicatie_id = ?""",
                (t.get("aanbestedingNaam", ""),
                 t.get("opdrachtgeverNaam", ""),
                 t.get("publicatieDatum", ""),
                 t.get("sluitingsDatum", ""),
                 t.get("typePublicatie", {}).get("code", ""),
                 t.get("typePublicatie", {}).get("omschrijving", ""),
                 t.get("procedure", {}).get("code", ""),
                 t.get("procedure", {}).get("omschrijving", ""),
                 t.get("typeOpdracht", {}).get("omschrijving", ""),
                 t.get("opdrachtBeschrijving", ""),
                 1 if t.get("europees") else 0,
                 1 if t.get("digitaal") else 0,
                 relevance, terms, tender_url,
                 analyse_path, status, pid))
            updated += 1
        else:
            conn.execute("""INSERT INTO tenders
                (publicatie_id, source, name, organisation, publication_date, closing_date,
                 type_publication_code, type_publication, procedure_code, procedure,
                 type_opdracht, description, is_european, is_digital,
                 relevance, search_terms, status, tender_url, analyse_path, raw_json)
                VALUES (?, 'tenderned', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)""",
                (pid,
                 t.get("aanbestedingNaam", ""),
                 t.get("opdrachtgeverNaam", ""),
                 t.get("publicatieDatum", ""),
                 t.get("sluitingsDatum", ""),
                 t.get("typePublicatie", {}).get("code", ""),
                 t.get("typePublicatie", {}).get("omschrijving", ""),
                 t.get("procedure", {}).get("code", ""),
                 t.get("procedure", {}).get("omschrijving", ""),
                 t.get("typeOpdracht", {}).get("omschrijving", ""),
                 t.get("opdrachtBeschrijving", ""),
                 1 if t.get("europees") else 0,
                 1 if t.get("digitaal") else 0,
                 relevance, terms, status, tender_url, analyse_path,
                 json.dumps(t, ensure_ascii=False)))
            inserted += 1

    return inserted, updated


def migrate_details(conn):
    """Enrich tenders from priority-tender-details.json (documents, extra metadata)."""
    path = os.path.join(RAW_DIR, "priority-tender-details.json")
    if not os.path.exists(path):
        print(f"Not found: {path}")
        return 0

    details = load_json(path)
    docs_added = 0

    for d in details:
        pid = str(d.get("publicatieId", d.get("publicatieid", "")))
        if not pid:
            continue

        row = conn.execute("SELECT id FROM tenders WHERE publicatie_id = ?", (pid,)).fetchone()
        if not row:
            continue
        tender_id = row[0]

        # Add documents
        for doc in d.get("_documenten", []):
            existing = conn.execute(
                "SELECT id FROM tender_documents WHERE tender_id = ? AND filename = ?",
                (tender_id, doc.get("naam", ""))
            ).fetchone()
            if not existing:
                conn.execute("""INSERT INTO tender_documents
                    (tender_id, filename, doc_type, file_size_kb, download_url, downloaded)
                    VALUES (?, ?, ?, ?, ?, ?)""",
                    (tender_id,
                     doc.get("naam", ""),
                     doc.get("type", ""),
                     doc.get("grootte_kb", 0),
                     doc.get("download_url", ""),
                     1))  # these were already downloaded
                docs_added += 1

    return docs_added


def migrate_doc_folders(conn):
    """Scan docs/ folder structure for downloaded documents and link to tenders."""
    if not os.path.exists(DOCS_DIR):
        return 0

    docs_added = 0
    for folder in os.listdir(DOCS_DIR):
        folder_path = os.path.join(DOCS_DIR, folder)
        if not os.path.isdir(folder_path):
            continue

        # Extract publicatieId from folder name (format: {id}-{name})
        parts = folder.split("-", 1)
        if not parts[0].isdigit():
            continue
        pid = parts[0]

        row = conn.execute("SELECT id FROM tenders WHERE publicatie_id = ?", (pid,)).fetchone()
        if not row:
            continue
        tender_id = row[0]

        # Update status
        conn.execute("UPDATE tenders SET status = CASE WHEN status = 'new' THEN 'downloaded' ELSE status END WHERE id = ?", (tender_id,))

        # Add files
        for fname in os.listdir(folder_path):
            if fname == "_metadata.json":
                continue
            existing = conn.execute(
                "SELECT id FROM tender_documents WHERE tender_id = ? AND filename = ?",
                (tender_id, fname)
            ).fetchone()
            if not existing:
                fpath = os.path.relpath(os.path.join(folder_path, fname), os.path.dirname(SCRIPT_DIR))
                fsize = os.path.getsize(os.path.join(folder_path, fname)) // 1024
                conn.execute("""INSERT INTO tender_documents
                    (tender_id, filename, filepath, file_size_kb, downloaded)
                    VALUES (?, ?, ?, ?, 1)""",
                    (tender_id, fname, fpath, fsize))
                docs_added += 1

    return docs_added


def main():
    if not os.path.exists(DB_PATH):
        print(f"Database not found: {DB_PATH}")
        print("Run create_db.py first.")
        return

    conn = sqlite3.connect(DB_PATH)
    conn.execute("PRAGMA foreign_keys=ON")

    print("Migrating all-tenders-combined.json...")
    inserted, updated = migrate_combined(conn)
    print(f"  Inserted: {inserted}, Updated: {updated}")

    print("Enriching from priority-tender-details.json...")
    docs = migrate_details(conn)
    print(f"  Documents added: {docs}")

    print("Scanning docs/ folder structure...")
    folder_docs = migrate_doc_folders(conn)
    print(f"  Documents from folders: {folder_docs}")

    conn.commit()

    # Summary
    total = conn.execute("SELECT COUNT(*) FROM tenders").fetchone()[0]
    analysed = conn.execute("SELECT COUNT(*) FROM tenders WHERE status = 'analysed'").fetchone()[0]
    downloaded = conn.execute("SELECT COUNT(*) FROM tenders WHERE status = 'downloaded'").fetchone()[0]
    total_docs = conn.execute("SELECT COUNT(*) FROM tender_documents").fetchone()[0]
    print(f"\nTotal tenders: {total}")
    print(f"  Analysed: {analysed}, Downloaded: {downloaded}, New: {total - analysed - downloaded}")
    print(f"  Documents: {total_docs}")

    conn.close()


if __name__ == "__main__":
    main()
