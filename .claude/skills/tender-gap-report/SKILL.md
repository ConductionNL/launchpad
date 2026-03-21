---
name: tender-gap-report
description: Generate ecosystem gap analysis report — identify software categories with tender demand but no Conduction product
user_invocable: true
---

# Tender Gap Report

Analyze the intelligence database to identify ecosystem gaps — software categories that appear in government tenders but have no corresponding Conduction product.

## Instructions

### Step 1: Query gaps from SQLite

```python
import sqlite3
from datetime import date

conn = sqlite3.connect('concurrentie-analyse/intelligence.db')

# Find categories with tenders but no product
gaps = conn.execute("""
    SELECT
        sc.slug, sc.name_nl, sc.name_en, sc.cpv_code,
        COUNT(t.id) as tender_count,
        ROUND(AVG(t.contract_value), 0) as avg_value,
        ROUND(SUM(t.contract_value), 0) as total_value,
        GROUP_CONCAT(DISTINCT t.organisation) as organisations
    FROM software_categories sc
    JOIN tenders t ON t.category_slug = sc.slug
    WHERE sc.conduction_product IS NULL
    GROUP BY sc.slug
    HAVING tender_count > 0
    ORDER BY tender_count DESC
""").fetchall()

# Also check unclassified tenders
unclassified = conn.execute(
    "SELECT COUNT(*) FROM tenders WHERE category_slug IS NULL"
).fetchone()[0]

conn.close()
```

### Step 2: Generate report

Create a markdown report at `concurrentie-analyse/reports/gap-report-{today}.md` with:

1. **Executive Summary** — Total gaps found, biggest opportunities
2. **Gap Table** — Category, tender count, estimated value, CPV code
3. **Per-Gap Details** — For top 5 gaps: list the specific tenders, organisations, and key requirements
4. **Recommendations** — Which gaps to investigate first (highest tender count + value)
5. **Coverage Map** — Show which categories ARE covered and by which product

### Step 3: Update ecosystem_gaps table

For each gap found, upsert into the `ecosystem_gaps` table with current tender counts and values.

### Step 4: Cross-reference with application-roadmap.md

Check if any identified gaps are already listed in `concurrentie-analyse/application-roadmap.md`. Note which gaps are truly new vs already being tracked.

### Step 5: Summary

Present the top 5 gaps to the user with a recommendation on which to investigate first using `/ecosystem:investigate`.
