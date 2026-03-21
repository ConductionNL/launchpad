#!/usr/bin/env python3
"""
Build the canonical feature catalog from TEC RFP templates + other sources.

This script:
1. Extracts TEC features into a proper hierarchical table (tec_features)
2. Maps ALL other sources to individual TEC features using keyword matching
3. Counts how many independent sources confirm each feature (importance score)

Run after sync_tec_rfp.py has imported Excel files.
Re-running is safe — it drops and rebuilds the mapping tables.

Usage:
    python3 build_feature_catalog.py [db_path]
"""

import os
import re
import sqlite3
import sys
from collections import defaultdict

DB_PATH = sys.argv[1] if len(sys.argv) > 1 else os.path.join(
    os.path.dirname(os.path.dirname(os.path.abspath(__file__))), "intelligence.db")


def create_tables(conn):
    """Create the canonical feature catalog tables."""
    conn.executescript("""
        -- Canonical features extracted from TEC RFP templates
        -- This is the REAL feature list per application type
        CREATE TABLE IF NOT EXISTS tec_features (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            category_slug TEXT NOT NULL,
            tec_code TEXT NOT NULL,          -- '1.4.1' hierarchical code
            name TEXT NOT NULL,              -- 'Commercial Account Management'
            description TEXT,
            level INTEGER NOT NULL,          -- depth: 1=module, 2=group, 3+=feature
            parent_code TEXT,                -- '1.4' parent code
            is_module INTEGER DEFAULT 0,     -- level 1-2 are modules/groups
            source_file TEXT,                -- which Excel it came from
            UNIQUE(category_slug, tec_code, name)
        );

        -- Individual source-to-feature mappings (replaces bulk links)
        -- Each row = "this source confirms this specific feature"
        CREATE TABLE IF NOT EXISTS feature_evidence (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tec_feature_id INTEGER NOT NULL,
            source_type TEXT NOT NULL,
            source_url TEXT,
            source_label TEXT,
            source_detail TEXT,
            match_method TEXT DEFAULT 'keyword', -- 'keyword', 'exact', 'manual'
            match_score REAL DEFAULT 0.0,        -- 0-1 confidence
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (tec_feature_id) REFERENCES tec_features(id)
        );

        CREATE INDEX IF NOT EXISTS idx_tec_features_cat ON tec_features(category_slug);
        CREATE INDEX IF NOT EXISTS idx_tec_features_code ON tec_features(tec_code);
        CREATE INDEX IF NOT EXISTS idx_tec_features_level ON tec_features(level);
        CREATE INDEX IF NOT EXISTS idx_feat_evidence_feature ON feature_evidence(tec_feature_id);
        CREATE INDEX IF NOT EXISTS idx_feat_evidence_type ON feature_evidence(source_type);
    """)


def extract_tec_features(conn):
    """Extract unique TEC features from feature_sources into tec_features table."""
    # Clear existing
    conn.execute("DELETE FROM tec_features")

    inserted = 0
    seen = set()

    for row in conn.execute("""
        SELECT DISTINCT category_slug, source_label, source_detail
        FROM feature_sources WHERE source_type = 'tec'
    """):
        cat, label, detail = row
        name = label.replace("TEC RFP: ", "").strip()

        # Extract code from detail
        code_match = re.match(r"Code:\s*([\d.]+)", detail or "")
        code = code_match.group(1) if code_match else ""

        if not code or not name:
            continue

        # Extract description (after "Code: X.Y.Z. ")
        desc = ""
        if detail:
            desc_match = re.match(r"Code:\s*[\d.]+\.\s*(.*)", detail)
            if desc_match:
                desc = desc_match.group(1).strip()

        key = (cat, code, name)
        if key in seen:
            continue
        seen.add(key)

        level = code.count('.') + 1
        parent_code = '.'.join(code.split('.')[:-1]) if '.' in code else ''
        is_module = 1 if '(module)' in name or level <= 2 else 0
        clean_name = name.replace(' (module)', '').strip()

        conn.execute("""
            INSERT OR IGNORE INTO tec_features
            (category_slug, tec_code, name, description, level, parent_code, is_module)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        """, (cat, code, clean_name, desc, level, parent_code, is_module))
        inserted += 1

    conn.commit()
    return inserted


def build_keyword_index(conn):
    """Build keyword lookup for each TEC feature."""
    features = conn.execute("""
        SELECT id, category_slug, name, description FROM tec_features
    """).fetchall()

    # For each feature, extract meaningful keywords
    index = {}  # (category_slug, keyword) -> [(feature_id, score)]
    for fid, cat, name, desc in features:
        text = f"{name} {desc or ''}".lower()

        # Clean and tokenize
        words = re.findall(r'[a-z]{3,}', text)
        # Also keep multi-word phrases
        phrases = []
        clean_name = name.lower().replace('(module)', '').strip()
        phrases.append(clean_name)
        # Split on common delimiters
        for part in re.split(r'[,/&]', clean_name):
            part = part.strip()
            if len(part) > 3:
                phrases.append(part)

        for word in words:
            key = (cat, word)
            if key not in index:
                index[key] = []
            index[key].append((fid, 0.3))  # word match = 0.3

        for phrase in phrases:
            key = (cat, phrase)
            if key not in index:
                index[key] = []
            index[key].append((fid, 0.8))  # phrase match = 0.8

    return index


def map_sources_to_features(conn, keyword_index):
    """Map all non-TEC sources to specific TEC features using keyword matching."""
    # Clear existing evidence
    conn.execute("DELETE FROM feature_evidence")

    # First, add TEC self-evidence (each TEC feature is its own source)
    conn.execute("""
        INSERT INTO feature_evidence (tec_feature_id, source_type, source_url, source_label, source_detail, match_method, match_score)
        SELECT tf.id, 'tec',
            'https://www3.technologyevaluation.com/selection-tools/p/rfp-templates',
            'TEC RFP: ' || tf.name,
            'Code: ' || tf.tec_code,
            'exact', 1.0
        FROM tec_features tf
    """)

    # Now map other sources
    source_types = conn.execute("""
        SELECT DISTINCT source_type FROM feature_sources WHERE source_type != 'tec'
    """).fetchall()

    total_mapped = 0

    for (stype,) in source_types:
        sources = conn.execute("""
            SELECT DISTINCT category_slug, source_url, source_label, source_detail
            FROM feature_sources WHERE source_type = ?
        """, (stype,)).fetchall()

        batch = []
        for cat, url, label, detail in sources:
            text = f"{label or ''} {detail or ''}".lower()
            words = set(re.findall(r'[a-z]{3,}', text))

            # Find matching features in this category
            matched_features = {}  # fid -> best_score

            for word in words:
                key = (cat, word)
                if key in keyword_index:
                    for fid, score in keyword_index[key]:
                        if fid not in matched_features or score > matched_features[fid]:
                            matched_features[fid] = score

            # Also try multi-word matching
            for phrase_len in range(2, 5):
                word_list = text.split()
                for i in range(len(word_list) - phrase_len + 1):
                    phrase = ' '.join(word_list[i:i + phrase_len])
                    key = (cat, phrase)
                    if key in keyword_index:
                        for fid, score in keyword_index[key]:
                            matched_features[fid] = max(matched_features.get(fid, 0), score)

            # Keep top matches (score > 0.3, max 5 per source)
            top_matches = sorted(matched_features.items(), key=lambda x: -x[1])[:5]

            for fid, score in top_matches:
                if score >= 0.3:
                    batch.append((fid, stype, url, label, detail, 'keyword', score))
                    total_mapped += 1

            # Batch insert every 1000
            if len(batch) >= 1000:
                conn.executemany("""
                    INSERT INTO feature_evidence
                    (tec_feature_id, source_type, source_url, source_label, source_detail, match_method, match_score)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                """, batch)
                batch = []

        # Final batch
        if batch:
            conn.executemany("""
                INSERT INTO feature_evidence
                (tec_feature_id, source_type, source_url, source_label, source_detail, match_method, match_score)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            """, batch)

        mapped_for_type = conn.execute(
            "SELECT COUNT(*) FROM feature_evidence WHERE source_type = ?", (stype,)
        ).fetchone()[0]
        print(f"  {stype:20s} → {mapped_for_type:>6} evidence links")

    conn.commit()
    return total_mapped


def compute_importance(conn):
    """Compute importance score per feature based on evidence count and diversity."""
    conn.execute("""
        CREATE TABLE IF NOT EXISTS feature_importance AS
        SELECT
            tf.id as tec_feature_id,
            tf.category_slug,
            tf.tec_code,
            tf.name,
            tf.level,
            tf.is_module,
            COUNT(fe.id) as evidence_count,
            COUNT(DISTINCT fe.source_type) as source_diversity,
            SUM(fe.match_score) as total_score,
            GROUP_CONCAT(DISTINCT fe.source_type) as source_types
        FROM tec_features tf
        LEFT JOIN feature_evidence fe ON fe.tec_feature_id = tf.id
        GROUP BY tf.id
    """)
    # If table already existed, rebuild it
    conn.execute("DROP TABLE IF EXISTS feature_importance")
    conn.execute("""
        CREATE TABLE feature_importance AS
        SELECT
            tf.id as tec_feature_id,
            tf.category_slug,
            tf.tec_code,
            tf.name,
            tf.level,
            tf.is_module,
            COUNT(fe.id) as evidence_count,
            COUNT(DISTINCT fe.source_type) as source_diversity,
            ROUND(SUM(fe.match_score), 1) as total_score,
            GROUP_CONCAT(DISTINCT fe.source_type) as source_types
        FROM tec_features tf
        LEFT JOIN feature_evidence fe ON fe.tec_feature_id = tf.id
        GROUP BY tf.id
    """)
    conn.execute("CREATE INDEX IF NOT EXISTS idx_importance_cat ON feature_importance(category_slug)")
    conn.execute("CREATE INDEX IF NOT EXISTS idx_importance_score ON feature_importance(total_score DESC)")
    conn.commit()


def main():
    conn = sqlite3.connect(DB_PATH)
    conn.execute("PRAGMA journal_mode=WAL")

    print("Step 1: Creating tables...")
    create_tables(conn)

    print("Step 2: Extracting TEC features...")
    tec_count = extract_tec_features(conn)
    print(f"  Extracted {tec_count} TEC features")

    print("Step 3: Building keyword index...")
    keyword_index = build_keyword_index(conn)
    print(f"  Built index with {len(keyword_index)} keyword entries")

    print("Step 4: Mapping sources to features...")
    evidence_count = map_sources_to_features(conn, keyword_index)
    print(f"  Created {evidence_count} evidence links")

    print("Step 5: Computing importance scores...")
    compute_importance(conn)

    # Summary
    print(f"\n=== FEATURE CATALOG COMPLETE ===\n")

    for r in conn.execute("""
        SELECT category_slug,
               COUNT(*) as total_features,
               SUM(CASE WHEN is_module = 0 THEN 1 ELSE 0 END) as leaf_features,
               SUM(evidence_count) as total_evidence,
               ROUND(AVG(source_diversity), 1) as avg_diversity
        FROM feature_importance
        GROUP BY category_slug
        ORDER BY total_features DESC
    """):
        print(f"  {r[0]:20s} {r[1]:>5} features ({r[2]:>4} leaf)  {r[3]:>6} evidence  avg {r[4]} source types")

    total_feats = conn.execute("SELECT COUNT(*) FROM tec_features").fetchone()[0]
    total_evidence = conn.execute("SELECT COUNT(*) FROM feature_evidence").fetchone()[0]
    print(f"\n  TOTAL: {total_feats} features, {total_evidence} evidence links")

    # Top 10 most-evidenced features
    print(f"\n=== TOP 20 FEATURES BY EVIDENCE ===\n")
    for r in conn.execute("""
        SELECT category_slug, tec_code, name, evidence_count, source_diversity, source_types
        FROM feature_importance
        WHERE is_module = 0
        ORDER BY evidence_count DESC
        LIMIT 20
    """):
        print(f"  [{r[0]:15s}] {r[2]:45s} {r[3]:>4} evidence, {r[4]} types ({r[5][:40]})")

    conn.close()


if __name__ == "__main__":
    main()
