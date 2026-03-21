---
name: intelligence:update
description: Pull latest data from external sources into the intelligence database
user_invocable: true
arguments:
  - name: source
    description: "Specific source to sync (or 'all' / 'due'). Options: wikidata-software, wikipedia-comparisons, awesome-selfhosted, dpg-registry, developers-italia, tenderned, github-issues, gemma-release"
    required: false
    default: "due"
---

# Intelligence Source Update

Sync external data sources into `concurrentie-analyse/intelligence.db`.

## Behavior

1. Read the `source_syncs` table to determine what needs updating
2. If `$ARGUMENTS.source` is:
   - `"due"` (default) — sync all sources whose `last_sync` + `sync_interval_hours` < now
   - `"all"` — sync every source regardless of last sync time
   - A specific source name — sync only that source
3. For each source to sync:
   - Set `status = 'running'` in `source_syncs`
   - Run the corresponding Python script from `concurrentie-analyse/scripts/sync/`
   - Update `source_syncs` with results (last_sync, records, status)
   - Report what was added/updated
4. Show a summary table at the end

## Source Scripts

Each source has a Python script at `concurrentie-analyse/scripts/sync/sync_{source_name}.py` that:
- Accepts the DB path as first argument
- Pulls data from the API
- Upserts into the appropriate tables
- Returns JSON to stdout: `{"records": N, "new": N, "updated": N, "errors": []}`

## Sync Intervals

| Source | Interval | Reason |
|--------|----------|--------|
| tenderned | 24h | New tenders daily |
| wikidata-software | 7 days | Slow-changing taxonomy |
| wikipedia-comparisons | 7 days | Comparison tables change slowly |
| awesome-selfhosted | 7 days | Weekly community updates |
| github-issues | 7 days | Feature requests trickle in |
| dpg-registry | 7 days | Small registry, slow growth |
| developers-italia | 7 days | Government catalog updates |
| gemma-release | yearly | Annual GEMMA release |

## Running

```bash
# Sync everything that's due
/intelligence:update

# Sync a specific source
/intelligence:update wikidata-software

# Force sync everything
/intelligence:update all
```

## Implementation

```python
import sqlite3, subprocess, json, sys
from datetime import datetime, timedelta

DB_PATH = "concurrentie-analyse/intelligence.db"
SCRIPTS_DIR = "concurrentie-analyse/scripts/sync"

source_filter = "$ARGUMENTS.source" or "due"

conn = sqlite3.connect(DB_PATH)

# Determine which sources to sync
if source_filter == "due":
    sources = conn.execute("""
        SELECT source_name, source_url, sync_interval_hours, last_sync
        FROM source_syncs
        WHERE status != 'running'
        AND (last_sync IS NULL
             OR datetime(last_sync, '+' || sync_interval_hours || ' hours') < datetime('now'))
    """).fetchall()
elif source_filter == "all":
    sources = conn.execute("""
        SELECT source_name, source_url, sync_interval_hours, last_sync
        FROM source_syncs WHERE status != 'running'
    """).fetchall()
else:
    sources = conn.execute("""
        SELECT source_name, source_url, sync_interval_hours, last_sync
        FROM source_syncs WHERE source_name = ?
    """, (source_filter,)).fetchall()

if not sources:
    print("All sources up to date. Nothing to sync.")
else:
    for name, url, interval, last in sources:
        print(f"\nSyncing {name}...")
        conn.execute("UPDATE source_syncs SET status='running' WHERE source_name=?", (name,))
        conn.commit()

        script = f"{SCRIPTS_DIR}/sync_{name.replace('-', '_')}.py"
        try:
            result = subprocess.run(
                ["python3", script, DB_PATH],
                capture_output=True, text=True, timeout=300
            )
            data = json.loads(result.stdout)
            conn.execute("""UPDATE source_syncs SET
                status='ok', last_sync=datetime('now'),
                last_sync_records=?, last_sync_new=?,
                updated_at=datetime('now'), error_message=NULL
                WHERE source_name=?""",
                (data["records"], data.get("new", 0), name))
            print(f"  OK: {data['records']} records ({data.get('new', 0)} new)")
        except Exception as e:
            conn.execute("""UPDATE source_syncs SET
                status='error', error_message=?,
                updated_at=datetime('now')
                WHERE source_name=?""", (str(e), name))
            print(f"  ERROR: {e}")
        conn.commit()

# Show summary
print("\n=== Sync Status ===")
for r in conn.execute("""SELECT source_name, status, last_sync, sync_interval_hours,
    last_sync_records, last_sync_new FROM source_syncs ORDER BY source_name"""):
    sync = r[2][:16] if r[2] else 'never'
    print(f"  {r[0]:25s} {r[1]:7s} {sync:18s} {r[4] or 0:>5} records ({r[5] or 0} new)")
conn.close()
```
