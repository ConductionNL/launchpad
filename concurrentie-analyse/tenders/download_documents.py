#!/usr/bin/env python3
"""Download all tender documents, organized per tender by publicatieId."""

import json
import os
import re
import time
import urllib.request

INPUT = os.path.join(os.path.dirname(__file__), "raw", "chatgpt-tender-batch.json")
DOCS_DIR = os.path.join(os.path.dirname(__file__), "docs")


def safe_filename(name, ext):
    """Make a filesystem-safe filename."""
    name = re.sub(r'[<>:"/\\|?*]', '_', name)
    name = re.sub(r'\s+', ' ', name).strip()
    name = name[:120]  # cap length
    if not name.lower().endswith(f".{ext}"):
        name = f"{name}.{ext}"
    return name


def download(url, path):
    """Download a file with retry."""
    for attempt in range(3):
        try:
            req = urllib.request.Request(url, headers={
                "Accept": "*/*",
                "User-Agent": "Mozilla/5.0 (tender-research)",
            })
            with urllib.request.urlopen(req, timeout=60) as resp:
                with open(path, "wb") as f:
                    f.write(resp.read())
            return True
        except Exception as e:
            if attempt < 2:
                time.sleep(2)
            else:
                print(f"    FAILED: {e}")
    return False


def main():
    with open(INPUT) as f:
        tenders = json.load(f)

    os.makedirs(DOCS_DIR, exist_ok=True)

    total_docs = sum(len(t.get("_documenten", [])) for t in tenders)
    downloaded = 0
    skipped = 0
    failed = 0

    print(f"Downloading documents for {len(tenders)} tenders ({total_docs} files total)\n")

    for i, tender in enumerate(tenders):
        pid = tender["publicatieId"]
        name = tender["aanbestedingNaam"]
        docs = tender.get("_documenten", [])

        if not docs:
            continue

        # Create tender directory: docs/{publicatieId}-{safe-name}/
        safe_name = re.sub(r'[<>:"/\\|?*]', '_', name)[:60].strip()
        tender_dir = os.path.join(DOCS_DIR, f"{pid}-{safe_name}")
        os.makedirs(tender_dir, exist_ok=True)

        # Save tender metadata as JSON for reference
        meta_path = os.path.join(tender_dir, "_metadata.json")
        if not os.path.exists(meta_path):
            with open(meta_path, "w") as f:
                json.dump({
                    "publicatieId": pid,
                    "aanbestedingNaam": name,
                    "opdrachtgeverNaam": tender.get("opdrachtgeverNaam", ""),
                    "publicatieDatum": tender.get("publicatieDatum", ""),
                    "typePublicatie": tender.get("typePublicatie", ""),
                    "opdrachtBeschrijving": tender.get("opdrachtBeschrijving", ""),
                    "_terms": tender.get("_terms", []),
                    "_relevance": tender.get("_relevance", []),
                    "tenderned_url": tender.get("tenderned_url", ""),
                    "documenten": docs,
                }, f, indent=2, ensure_ascii=False)

        print(f"[{i+1}/{len(tenders)}] T-{pid}: {name[:55]} ({len(docs)} docs)")

        for doc in docs:
            filename = safe_filename(doc["naam"], doc["type"])
            filepath = os.path.join(tender_dir, filename)

            # Skip if already downloaded
            if os.path.exists(filepath) and os.path.getsize(filepath) > 0:
                skipped += 1
                continue

            print(f"  ↓ {filename[:70]}...", end=" ", flush=True)
            if download(doc["download_url"], filepath):
                size_kb = os.path.getsize(filepath) // 1024
                print(f"{size_kb} KB")
                downloaded += 1
            else:
                failed += 1
            time.sleep(0.2)

    print(f"\n{'='*60}")
    print(f"DONE")
    print(f"  Downloaded: {downloaded}")
    print(f"  Skipped (already exists): {skipped}")
    print(f"  Failed: {failed}")
    print(f"  Total on disk: {downloaded + skipped}")

    # Print total disk usage
    total_size = 0
    for root, dirs, files in os.walk(DOCS_DIR):
        for f in files:
            total_size += os.path.getsize(os.path.join(root, f))
    print(f"  Disk usage: {total_size / (1024*1024):.0f} MB")
    print(f"  Location: {DOCS_DIR}/")


if __name__ == "__main__":
    main()
