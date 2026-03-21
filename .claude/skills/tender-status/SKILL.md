---
name: tender-status
description: Show tender intelligence dashboard — totals by source, category, status, gaps, and recent activity
user_invocable: true
---

# Tender Status Dashboard

Show a quick overview of the tender intelligence database.

## Instructions

1. Read the SQLite database at `concurrentie-analyse/intelligence.db`
2. Run these queries and present as a formatted dashboard:

```python
import sqlite3
conn = sqlite3.connect('concurrentie-analyse/intelligence.db')

# Total tenders by source
print("=== Tenders by Source ===")
for row in conn.execute("SELECT source, COUNT(*) FROM tenders GROUP BY source ORDER BY COUNT(*) DESC"):
    print(f"  {row[0]}: {row[1]}")

# By status
print("\n=== Tenders by Status ===")
for row in conn.execute("SELECT status, COUNT(*) FROM tenders GROUP BY status ORDER BY COUNT(*) DESC"):
    print(f"  {row[0]}: {row[1]}")

# By category (top 15)
print("\n=== Top Categories ===")
for row in conn.execute("""
    SELECT COALESCE(t.category_slug, 'unclassified'), COUNT(*), sc.conduction_product
    FROM tenders t
    LEFT JOIN software_categories sc ON t.category_slug = sc.slug
    GROUP BY t.category_slug
    ORDER BY COUNT(*) DESC LIMIT 15
"""):
    covered = f" → {row[2]}" if row[2] else " (GAP)"
    print(f"  {row[0]}: {row[1]} tenders{covered}")

# Requirements summary
print("\n=== Requirements ===")
row = conn.execute("SELECT COUNT(*), SUM(CASE WHEN type='eis' THEN 1 ELSE 0 END), SUM(CASE WHEN type='wens' THEN 1 ELSE 0 END) FROM requirements").fetchone()
print(f"  Total: {row[0]} (eisen: {row[1]}, wensen: {row[2]})")

# Competitors
print("\n=== Competitors ===")
for row in conn.execute("SELECT product_line, COUNT(*) FROM competitors GROUP BY product_line"):
    print(f"  {row[0]}: {row[1]}")

# Top integration systems
print("\n=== Top 10 Integration Systems ===")
for row in conn.execute("SELECT system, COUNT(*) FROM integrations GROUP BY system ORDER BY COUNT(*) DESC LIMIT 10"):
    print(f"  {row[0]}: {row[1]}")

# Ecosystem gaps
print("\n=== Ecosystem Gaps ===")
for row in conn.execute("""
    SELECT sc.slug, sc.name_en, sc.conduction_product
    FROM software_categories sc
    WHERE sc.conduction_product IS NULL
    AND sc.parent_slug IS NOT NULL
    AND sc.slug NOT IN (SELECT COALESCE(parent_slug,'') FROM software_categories)
    ORDER BY sc.slug
"""):
    print(f"  {row[0]}: {row[1]}")

conn.close()
```

3. Present the results as a clean markdown table/dashboard
4. Highlight any categories with >5 unclassified tenders
5. Note the number of tenders that still need classification
