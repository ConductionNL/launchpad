---
name: tender-scan
description: Scrape TenderNed for new tenders, classify them by software category using Qwen, and update the intelligence database
user_invocable: true
---

# Tender Scan

Run the TenderNed scraper to fetch new tenders, then classify unclassified tenders using the local Qwen model.

## Instructions

### Step 1: Scrape TenderNed

Run the existing scraper to fetch fresh data:

```bash
python3 concurrentie-analyse/tenders/scrape_tenderned.py
```

### Step 2: Import new tenders into SQLite

```bash
python3 concurrentie-analyse/scripts/migrate_raw_tenders.py
```

### Step 3: Classify unclassified tenders

Query the database for tenders without a `category_slug`, then classify each using Qwen:

```python
import sqlite3, json, urllib.request

conn = sqlite3.connect('concurrentie-analyse/intelligence.db')
unclassified = conn.execute(
    "SELECT id, publicatie_id, name, description FROM tenders WHERE category_slug IS NULL LIMIT 50"
).fetchall()

CATEGORIES = "zaaksysteem, vth, dms, crm, objectregistratie, formulieren, boekhouding, erp, hrm, projectmanagement, gis, e-facturatie, iam, archivering, website-cms, participatie, meldingen, subsidies, inkoop, contractbeheer, planning, bi-reporting, integratie, software"

for tender_id, pid, name, desc in unclassified:
    prompt = f"Classify this Dutch government tender into ONE category from: {CATEGORIES}\nName: {name}\nDescription: {(desc or '')[:500]}\nRespond with ONLY the slug."

    data = json.dumps({
        "model": "qwen3.5-optimized",
        "messages": [{"role": "user", "content": prompt}],
        "temperature": 0.1,
        "stream": False
    }).encode()

    req = urllib.request.Request("http://localhost:11434/v1/chat/completions",
        data=data, headers={"Content-Type": "application/json"})
    resp = json.loads(urllib.request.urlopen(req, timeout=30).read())
    category = resp["choices"][0]["message"]["content"].strip().lower()

    # Validate against known categories
    valid = conn.execute("SELECT slug FROM software_categories WHERE slug = ?", (category,)).fetchone()
    if valid:
        conn.execute("UPDATE tenders SET category_slug = ?, status = 'classified' WHERE id = ?",
                     (category, tender_id))
        print(f"  {pid}: {name[:50]} → {category}")
    else:
        print(f"  {pid}: {name[:50]} → UNKNOWN: {category}")

conn.commit()
conn.close()
```

### Step 4: Report

After classification, run `/tender:status` to see the updated dashboard.

Show a summary of:
- New tenders found
- Tenders classified
- Top categories
- Any new gaps detected
