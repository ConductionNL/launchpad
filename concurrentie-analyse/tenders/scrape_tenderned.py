#!/usr/bin/env python3
"""Scrape TenderNed API for relevant tenders — focused search terms only."""

import json
import time
import urllib.request
import urllib.parse
import os

BASE_URL = "https://www.tenderned.nl/papi/tenderned-rs-tns/v2/publicaties"
DETAIL_URL = "https://www.tenderned.nl/papi/tenderned-rs-tns/v2/publicaties/{}"
DOCS_URL = "https://www.tenderned.nl/papi/tenderned-rs-tns/v2/publicaties/{}/documenten"
OUTPUT_DIR = os.path.join(os.path.dirname(__file__), "raw")

# Only specific terms that won't return 60K+ noise.
# (max_pages=None means fetch all; a number caps it)
SEARCH_TERMS = [
    # Procest — specific terms
    {"term": "zaaksysteem", "relevance": "procest", "max_pages": None},
    {"term": "zaakafhandel", "relevance": "procest", "max_pages": None},
    {"term": "zaakafhandelcomponent", "relevance": "procest", "max_pages": None},
    {"term": "zaakgericht", "relevance": "procest", "max_pages": 5},
    {"term": "VTH-systeem", "relevance": "procest", "max_pages": 3},
    {"term": "VTH-applicatie", "relevance": "procest", "max_pages": 3},
    {"term": "open formulieren", "relevance": "procest", "max_pages": 3},
    {"term": "BPMN", "relevance": "procest", "max_pages": 3},

    # Pipelinq — specific terms
    {"term": "klantinteractie", "relevance": "pipelinq", "max_pages": 3},
    {"term": "klantcontactsysteem", "relevance": "pipelinq", "max_pages": 3},
    {"term": "CRM-systeem", "relevance": "pipelinq", "max_pages": 3},
    {"term": "contactcenter gemeente", "relevance": "pipelinq", "max_pages": 3},

    # Both / architecture
    {"term": "common ground software", "relevance": "both", "max_pages": 3},
    {"term": "objectregistratie", "relevance": "both", "max_pages": 3},
]

PAGE_SIZE = 50


def fetch_json(url):
    for attempt in range(3):
        try:
            req = urllib.request.Request(url, headers={"Accept": "application/json"})
            with urllib.request.urlopen(req, timeout=30) as resp:
                return json.loads(resp.read().decode("utf-8"))
        except Exception as e:
            print(f"  retry {attempt+1}: {e}")
            time.sleep(2)
    return None


def scrape_term(term, max_pages=None):
    all_results = []
    page = 0
    while True:
        params = urllib.parse.urlencode({
            "page": page, "size": PAGE_SIZE,
            "search": term, "sort": "relevantie",
        })
        url = f"{BASE_URL}?{params}"
        print(f"  p{page}", end="", flush=True)
        data = fetch_json(url)
        if not data or "content" not in data:
            print(" FAIL", end="")
            break
        results = data["content"]
        total = data.get("totalElements", 0)
        all_results.extend(results)
        total_pages = data.get("totalPages", 1)
        # Stop conditions
        if page >= total_pages - 1:
            break
        if max_pages and page >= max_pages - 1:
            print(f" (capped at {max_pages} pages of {total_pages})", end="")
            break
        page += 1
        time.sleep(0.3)
    return all_results


def main():
    os.makedirs(OUTPUT_DIR, exist_ok=True)
    all_tenders = {}

    for entry in SEARCH_TERMS:
        term = entry["term"]
        relevance = entry["relevance"]
        max_pages = entry.get("max_pages")
        print(f"\n[{relevance}] '{term}'", end=" → ")

        results = scrape_term(term, max_pages)
        print(f" = {len(results)} results")

        # Save per-term
        safe = term.replace(" ", "-").lower()
        with open(os.path.join(OUTPUT_DIR, f"tn-{safe}.json"), "w") as f:
            json.dump(results, f, indent=2, ensure_ascii=False)

        # Dedup
        for r in results:
            pid = r["publicatieId"]
            if pid not in all_tenders:
                r["_terms"] = [term]
                r["_relevance"] = [relevance]
                all_tenders[pid] = r
            else:
                if term not in all_tenders[pid].get("_terms", []):
                    all_tenders[pid].setdefault("_terms", []).append(term)
                if relevance not in all_tenders[pid].get("_relevance", []):
                    all_tenders[pid].setdefault("_relevance", []).append(relevance)

        time.sleep(0.5)

    # Sort by date descending
    combined = sorted(all_tenders.values(),
                      key=lambda x: x.get("publicatieDatum", ""), reverse=True)
    with open(os.path.join(OUTPUT_DIR, "all-tenders-combined.json"), "w") as f:
        json.dump(combined, f, indent=2, ensure_ascii=False)

    print(f"\n{'='*60}")
    print(f"Total unique tenders: {len(combined)}")

    # Filter priority tenders: matched specific zaak/klant terms, or matched multiple terms
    specific_terms = {
        "zaaksysteem", "zaakafhandel", "zaakafhandelcomponent",
        "klantinteractie", "klantcontactsysteem", "klantinteractiesysteem",
        "VTH-systeem", "objectregistratie",
    }
    priority = [
        t for t in combined
        if len(t.get("_terms", [])) > 1
        or any(term in specific_terms for term in t.get("_terms", []))
    ]
    print(f"Priority tenders (for detail fetch): {len(priority)}")

    # Fetch details + document lists for priority tenders
    details = []
    for i, t in enumerate(priority):
        pid = t["publicatieId"]
        name = t.get("aanbestedingNaam", "?")[:55]
        print(f"  [{i+1}/{len(priority)}] {pid} {name}...", end=" ", flush=True)
        detail = fetch_json(DETAIL_URL.format(pid))
        if detail:
            detail["_terms"] = t.get("_terms", [])
            detail["_relevance"] = t.get("_relevance", [])
            # Fetch document list with download URLs
            docs = fetch_json(DOCS_URL.format(pid))
            if docs and "documenten" in docs:
                detail["_documenten"] = [
                    {
                        "naam": d["documentNaam"],
                        "type": d["typeDocument"]["code"],
                        "grootte_kb": d["grootte"] // 1024,
                        "categorie": d.get("publicatieCategorie", {}).get("omschrijving", ""),
                        "download_url": f"https://www.tenderned.nl{d['links']['download']['href']}",
                    }
                    for d in docs["documenten"]
                ]
            details.append(detail)
            print(f"OK ({len(detail.get('_documenten', []))} docs)")
        else:
            print("FAIL")
        time.sleep(0.3)

    with open(os.path.join(OUTPUT_DIR, "priority-tender-details.json"), "w") as f:
        json.dump(details, f, indent=2, ensure_ascii=False)

    # Quick summary
    print(f"\n{'='*60}")
    print(f"DONE — {len(combined)} unique, {len(details)} details fetched")
    print(f"Files in: {OUTPUT_DIR}/")

    # Print top tenders by term overlap
    multi = [t for t in combined if len(t.get("_terms", [])) > 1]
    if multi:
        print(f"\nTenders matching multiple search terms ({len(multi)}):")
        for t in multi[:20]:
            print(f"  [{t['publicatieId']}] {t.get('aanbestedingNaam','?')[:60]}")
            print(f"    Terms: {', '.join(t['_terms'])}")


if __name__ == "__main__":
    main()
