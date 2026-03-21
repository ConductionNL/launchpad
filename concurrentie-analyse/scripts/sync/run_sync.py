#!/usr/bin/env python3
"""
Unified sync runner — pulls data from all configured external sources.

Usage:
    python3 run_sync.py                  # sync everything that's due
    python3 run_sync.py all              # force sync everything
    python3 run_sync.py wikidata-software  # sync specific source
    python3 run_sync.py --status         # show sync status only

This script is designed to run without Claude — zero token cost.
Schedule it via cron, n8n, or run manually.
"""

import json
import os
import sqlite3
import subprocess
import sys
from datetime import datetime

SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
DB_PATH = os.path.join(SCRIPT_DIR, "..", "..", "intelligence.db")


def get_sources(conn, source_filter):
    """Get list of sources to sync based on filter."""
    if source_filter == "due":
        return conn.execute("""
            SELECT source_name, source_url, sync_interval_hours, last_sync
            FROM source_syncs
            WHERE status != 'running'
            AND (last_sync IS NULL
                 OR datetime(last_sync, '+' || sync_interval_hours || ' hours') < datetime('now'))
        """).fetchall()
    elif source_filter == "all":
        return conn.execute("""
            SELECT source_name, source_url, sync_interval_hours, last_sync
            FROM source_syncs WHERE status != 'running'
        """).fetchall()
    else:
        return conn.execute("""
            SELECT source_name, source_url, sync_interval_hours, last_sync
            FROM source_syncs WHERE source_name = ?
        """, (source_filter,)).fetchall()


def sync_source(conn, name):
    """Run the sync script for a given source. Returns result dict."""
    script_name = f"sync_{name.replace('-', '_')}.py"
    script_path = os.path.join(SCRIPT_DIR, script_name)

    if not os.path.exists(script_path):
        return {"error": f"Script not found: {script_name}", "records": 0}

    conn.execute("UPDATE source_syncs SET status='running', updated_at=datetime('now') WHERE source_name=?", (name,))
    conn.commit()

    try:
        result = subprocess.run(
            [sys.executable, script_path, DB_PATH],
            capture_output=True, text=True, timeout=300
        )
        if result.returncode != 0:
            error = result.stderr[:500] if result.stderr else f"Exit code {result.returncode}"
            return {"error": error, "records": 0}

        data = json.loads(result.stdout)
        return data

    except subprocess.TimeoutExpired:
        return {"error": "Timeout after 300s", "records": 0}
    except json.JSONDecodeError:
        return {"error": f"Invalid JSON output: {result.stdout[:200]}", "records": 0}
    except Exception as e:
        return {"error": str(e), "records": 0}


def show_status(conn):
    """Print sync status table."""
    print(f"\n{'Source':<28} {'Status':<8} {'Last Sync':<20} {'Interval':>8} {'Records':>8} {'New':>6}")
    print("-" * 85)
    for r in conn.execute("""
        SELECT source_name, status, last_sync, sync_interval_hours,
               last_sync_records, last_sync_new, error_message
        FROM source_syncs ORDER BY source_name
    """):
        sync_time = r[2][:16] if r[2] else "never"
        interval = f"{r[3]}h" if r[3] < 168 else f"{r[3]//24}d" if r[3] < 8760 else f"{r[3]//8760}y"
        status_icon = {"ok": "OK", "error": "ERR", "never": "---", "running": "RUN"}.get(r[1], r[1])
        print(f"  {r[0]:<26} {status_icon:<8} {sync_time:<20} {interval:>8} {r[4] or 0:>8} {r[5] or 0:>6}")
        if r[1] == "error" and r[6]:
            print(f"    Error: {r[6][:80]}")


def main():
    source_filter = sys.argv[1] if len(sys.argv) > 1 else "due"

    conn = sqlite3.connect(DB_PATH)

    if source_filter == "--status":
        show_status(conn)
        conn.close()
        return

    sources = get_sources(conn, source_filter)

    if not sources:
        print("All sources up to date. Nothing to sync.")
        show_status(conn)
        conn.close()
        return

    print(f"Syncing {len(sources)} source(s)...\n")

    for name, url, interval, last_sync in sources:
        print(f"  {name}...", end=" ", flush=True)
        result = sync_source(conn, name)

        if "error" in result and result["error"]:
            conn.execute("""UPDATE source_syncs SET
                status='error', error_message=?, updated_at=datetime('now')
                WHERE source_name=?""", (result["error"][:500], name))
            print(f"ERROR: {result['error'][:60]}")
        else:
            records = result.get("records", 0)
            new = result.get("new", 0)
            conn.execute("""UPDATE source_syncs SET
                status='ok', last_sync=datetime('now'),
                last_sync_records=?, last_sync_new=?,
                updated_at=datetime('now'), error_message=NULL
                WHERE source_name=?""", (records, new, name))
            print(f"OK ({records} records, {new} new)")

        conn.commit()

    show_status(conn)
    conn.close()


if __name__ == "__main__":
    main()
