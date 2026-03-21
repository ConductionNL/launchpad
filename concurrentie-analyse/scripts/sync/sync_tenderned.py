#!/usr/bin/env python3
"""Sync TenderNed tenders into SQLite — wraps the existing scraper logic."""

import json
import sqlite3
import sys
import time
import urllib.request
import urllib.parse

DB_PATH = sys.argv[1] if len(sys.argv) > 1 else "concurrentie-analyse/intelligence.db"

BASE_URL = "https://www.tenderned.nl/papi/tenderned-rs-tns/v2/publicaties"
PAGE_SIZE = 50

# Expanded search terms covering all our software categories
SEARCH_TERMS = [
    # Procest (case management, VTH, forms)
    {"term": "zaaksysteem", "relevance": "procest", "max_pages": None},
    {"term": "zaakafhandel", "relevance": "procest", "max_pages": None},
    {"term": "zaakafhandelcomponent", "relevance": "procest", "max_pages": None},
    {"term": "zaakgericht", "relevance": "procest", "max_pages": 5},
    {"term": "VTH-systeem", "relevance": "procest", "max_pages": 3},
    {"term": "VTH-applicatie", "relevance": "procest", "max_pages": 3},
    {"term": "open formulieren", "relevance": "procest", "max_pages": 3},
    {"term": "BPMN", "relevance": "procest", "max_pages": 3},
    # Pipelinq (CRM, customer interaction)
    {"term": "klantinteractie", "relevance": "pipelinq", "max_pages": 3},
    {"term": "klantcontactsysteem", "relevance": "pipelinq", "max_pages": 3},
    {"term": "CRM-systeem", "relevance": "pipelinq", "max_pages": 3},
    {"term": "contactcenter gemeente", "relevance": "pipelinq", "max_pages": 3},
    # Both / architecture
    {"term": "common ground software", "relevance": "both", "max_pages": 3},
    {"term": "objectregistratie", "relevance": "both", "max_pages": 3},
    # New: ecosystem gap categories
    {"term": "boekhoudsoftware gemeente", "relevance": "boekhouding", "max_pages": 3},
    {"term": "financieel systeem gemeente", "relevance": "boekhouding", "max_pages": 3},
    {"term": "ERP gemeente", "relevance": "erp", "max_pages": 3},
    {"term": "HRM systeem gemeente", "relevance": "hrm", "max_pages": 3},
    {"term": "personeelsinformatie gemeente", "relevance": "hrm", "max_pages": 3},
    {"term": "projectmanagementsoftware", "relevance": "projectmanagement", "max_pages": 3},
    {"term": "GIS systeem gemeente", "relevance": "gis", "max_pages": 3},
    {"term": "geo-informatie gemeente", "relevance": "gis", "max_pages": 3},
    {"term": "e-facturatie gemeente", "relevance": "e-facturatie", "max_pages": 3},
    {"term": "subsidiesysteem gemeente", "relevance": "subsidies", "max_pages": 3},
    {"term": "inkoopsysteem gemeente", "relevance": "inkoop", "max_pages": 3},
    {"term": "participatieplatform gemeente", "relevance": "participatie", "max_pages": 3},
    {"term": "meldingen openbare ruimte", "relevance": "meldingen", "max_pages": 3},
    {"term": "archiveringssysteem gemeente", "relevance": "archivering", "max_pages": 3},
    {"term": "e-depot gemeente", "relevance": "archivering", "max_pages": 3},
    {"term": "documentmanagementsysteem gemeente", "relevance": "dms", "max_pages": 3},
    {"term": "website cms gemeente", "relevance": "website-cms", "max_pages": 3},
    {"term": "contractbeheer gemeente", "relevance": "contractbeheer", "max_pages": 3},
    {"term": "BI rapportage gemeente", "relevance": "bi-reporting", "max_pages": 3},
]


def fetch_page(term, page=0):
    """Fetch one page of TenderNed results."""
    params = urllib.parse.urlencode({
        "searchTerm": term,
        "page": page,
        "size": PAGE_SIZE,
        "sort": "publicatieDatum,desc"
    })
    url = f"{BASE_URL}?{params}"
    req = urllib.request.Request(url, headers={"User-Agent": "ConductionIntelligence/1.0"})
    try:
        with urllib.request.urlopen(req, timeout=30) as resp:
            data = json.loads(resp.read().decode())
        return data
    except Exception as e:
        print(f"Error fetching {term} page {page}: {e}", file=sys.stderr)
        return None


def upsert_tender(conn, tender, relevance, search_term):
    """Insert or update a tender in the database."""
    pub_id = str(tender.get("publicatieId", ""))
    if not pub_id:
        return False

    existing = conn.execute("SELECT id FROM tenders WHERE publicatie_id = ?", (pub_id,)).fetchone()

    name = tender.get("aanbestedingNaam", "")
    org = tender.get("opdrachtgeverNaam", "")
    pub_date = tender.get("publicatieDatum", "")
    closing = tender.get("sluitingsDatum", "")
    type_pub_code = tender.get("typePublicatie", {}).get("code", "")
    type_pub = tender.get("typePublicatie", {}).get("omschrijving", "")
    proc_code = tender.get("procedure", {}).get("code", "")
    procedure = tender.get("procedure", {}).get("omschrijving", "")
    type_opdracht = tender.get("typeOpdracht", {}).get("omschrijving", "")
    desc = tender.get("opdrachtBeschrijving", "")
    is_eu = 1 if tender.get("europees") else 0
    is_digital = 1 if tender.get("digitaal") else 0
    link = tender.get("link", {}).get("href", "")

    if existing:
        # Update search terms if new term found
        conn.execute("""UPDATE tenders SET updated_at = datetime('now')
            WHERE publicatie_id = ?""", (pub_id,))
        return False
    else:
        conn.execute("""INSERT INTO tenders
            (publicatie_id, source, name, organisation, publication_date, closing_date,
             type_publication_code, type_publication, procedure_code, procedure,
             type_opdracht, description, is_european, is_digital, relevance,
             search_terms, tender_url, raw_json, status)
            VALUES (?, 'tenderned', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'new')
        """, (pub_id, name, org, pub_date, closing, type_pub_code, type_pub,
              proc_code, procedure, type_opdracht, desc, is_eu, is_digital,
              relevance, json.dumps([search_term]), link,
              json.dumps(tender, ensure_ascii=False)))
        return True


def main():
    conn = sqlite3.connect(DB_PATH)
    total = 0
    new_count = 0
    errors = []

    for st in SEARCH_TERMS:
        term = st["term"]
        relevance = st["relevance"]
        max_pages = st.get("max_pages")
        page = 0

        while True:
            data = fetch_page(term, page)
            if not data:
                errors.append(f"Failed to fetch {term} page {page}")
                break

            content = data if isinstance(data, list) else data.get("content", data.get("_embedded", {}).get("publicaties", []))
            if not content:
                break

            for tender in content:
                total += 1
                if upsert_tender(conn, tender, relevance, term):
                    new_count += 1

            conn.commit()

            # Check if there are more pages
            total_pages = data.get("totalPages", 1) if isinstance(data, dict) else 1
            page += 1
            if page >= total_pages:
                break
            if max_pages and page >= max_pages:
                break

            time.sleep(0.5)  # Be nice to the API

    conn.commit()
    conn.close()

    print(json.dumps({
        "records": total,
        "new": new_count,
        "search_terms": len(SEARCH_TERMS),
        "errors": errors
    }))


if __name__ == "__main__":
    main()
