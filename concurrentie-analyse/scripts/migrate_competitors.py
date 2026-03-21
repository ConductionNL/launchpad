#!/usr/bin/env python3
"""Migrate competitor MERGED-ANALYSIS.md files into SQLite."""

import os
import re
import sqlite3

SCRIPT_DIR = os.path.dirname(__file__)
DB_PATH = os.path.join(os.path.dirname(SCRIPT_DIR), "intelligence.db")
BASE_DIR = os.path.dirname(SCRIPT_DIR)

PRODUCT_LINES = {
    "openregister": "openregister",
    "pipelinq": "pipelinq",
    "procest": "procest",
}

# Patterns for parsing MERGED-ANALYSIS.md header
RE_URL = re.compile(r'\*\*(?:Competitor|Product):\*\*\s*(?:.*?)\((https?://[^\s)]+)\)')
RE_REPO = re.compile(r'\*\*Repository:\*\*\s*(https?://[^\s)]+)')
RE_STARS = re.compile(r'(\d[\d,.]*k?\+?)\s*stars', re.IGNORECASE)
RE_LICENSE = re.compile(r'\*\*License:\*\*\s*(.+?)(?:\s*[-–—]|$)', re.MULTILINE)
RE_VERSION = re.compile(r'\*\*Version\s*(?:Analyzed)?:\*\*\s*(.+)', re.MULTILINE)

# Simple patterns for pricing and tech stack
RE_PRICING = re.compile(r'(?:free|open.?source|€\d|pricing|plan)', re.IGNORECASE)


def parse_stars(text):
    """Parse star count like '29k+' into integer."""
    match = RE_STARS.search(text)
    if not match:
        return None
    s = match.group(1).replace(",", "").replace("+", "")
    if s.lower().endswith("k"):
        return int(float(s[:-1]) * 1000)
    return int(float(s))


def extract_header_info(text):
    """Extract metadata from the header section of MERGED-ANALYSIS.md."""
    info = {}

    url_match = RE_URL.search(text[:2000])
    if url_match:
        info["url"] = url_match.group(1)

    repo_match = RE_REPO.search(text[:2000])
    if repo_match:
        info["github_url"] = repo_match.group(1)

    info["github_stars"] = parse_stars(text[:2000])

    license_match = RE_LICENSE.search(text[:2000])
    if license_match:
        info["license"] = license_match.group(1).strip()

    version_match = RE_VERSION.search(text[:2000])
    if version_match:
        info["version"] = version_match.group(1).strip()

    return info


def extract_tech_stack(text):
    """Try to find tech stack info."""
    # Look for tech stack table or section
    tech_section = ""
    for marker in ["Tech Stack", "Technology", "Stack"]:
        idx = text.lower().find(marker.lower())
        if idx >= 0:
            tech_section = text[idx:idx + 1000]
            break
    if not tech_section:
        return None

    # Extract tech names from table rows or bullet points
    techs = []
    for line in tech_section.split("\n")[:20]:
        # Table: | Layer | Technology |
        parts = [p.strip() for p in line.split("|") if p.strip()]
        if len(parts) >= 2 and not parts[0].startswith("---"):
            techs.append(f"{parts[0]}: {parts[1]}")
    return "; ".join(techs[:10]) if techs else None


def extract_pricing_model(text):
    """Determine pricing model from the text."""
    lower = text.lower()
    if "open-source" in lower or "open source" in lower:
        if "enterprise" in lower or "premium" in lower or "cloud" in lower:
            return "freemium"
        return "open-source"
    if "saas" in lower and "self-hosted" in lower:
        return "freemium"
    if "saas" in lower:
        return "saas"
    if "proprietary" in lower or "commercial" in lower:
        return "proprietary"
    return "unknown"


def extract_features(text, competitor_dir):
    """Extract feature list from MERGED-ANALYSIS.md or specs directory."""
    features = []

    # Try specs directory
    specs_dir = os.path.join(competitor_dir, "specs")
    if os.path.isdir(specs_dir):
        for spec_folder in sorted(os.listdir(specs_dir)):
            spec_path = os.path.join(specs_dir, spec_folder, "spec.md")
            if os.path.exists(spec_path):
                # Read first few lines for title
                with open(spec_path, "r", encoding="utf-8") as f:
                    first_lines = f.read(500)
                title_match = re.search(r'^#\s+(.+)', first_lines, re.MULTILINE)
                title = title_match.group(1) if title_match else spec_folder.replace("-", " ").title()
                features.append({
                    "name": title,
                    "category": spec_folder,
                    "spec_path": os.path.relpath(spec_path, BASE_DIR),
                })

    # Also try to find feature inventory table in MERGED-ANALYSIS
    feature_section_idx = text.lower().find("feature inventory")
    if feature_section_idx < 0:
        feature_section_idx = text.lower().find("feature comparison")
    if feature_section_idx >= 0:
        section = text[feature_section_idx:feature_section_idx + 3000]
        # Parse table rows: | # | Name | Description |
        for line in section.split("\n"):
            parts = [p.strip() for p in line.split("|") if p.strip()]
            if len(parts) >= 2 and parts[0].isdigit():
                name = parts[1]
                desc = parts[2] if len(parts) > 2 else ""
                # Don't duplicate if already from specs
                if not any(f["name"] == name for f in features):
                    features.append({
                        "name": name,
                        "category": None,
                        "description": desc,
                        "spec_path": None,
                    })

    return features


def detect_language(tech_stack, text):
    """Detect primary programming language."""
    if not tech_stack:
        tech_stack = ""
    combined = (tech_stack + " " + text[:3000]).lower()
    langs = {
        "python": "Python",
        "django": "Python",
        "flask": "Python",
        "javascript": "JavaScript",
        "typescript": "TypeScript",
        "node.js": "JavaScript",
        "java": "Java",
        "spring": "Java",
        "php": "PHP",
        "laravel": "PHP",
        "ruby": "Ruby",
        "rails": "Ruby",
        "go ": "Go",
        "golang": "Go",
        "rust": "Rust",
        ".net": "C#",
        "c#": "C#",
    }
    for key, lang in langs.items():
        if key in combined:
            return lang
    return None


def process_competitor(product_line, competitor_slug, competitor_dir):
    """Parse a single competitor directory."""
    merged_path = os.path.join(competitor_dir, "MERGED-ANALYSIS.md")

    if not os.path.exists(merged_path):
        # Try overview.md or the slug.md as fallback
        for alt in [f"{competitor_slug}.md", "overview.md"]:
            alt_path = os.path.join(competitor_dir, alt)
            if os.path.exists(alt_path):
                merged_path = alt_path
                break

    if not os.path.exists(merged_path):
        return None

    with open(merged_path, "r", encoding="utf-8") as f:
        text = f.read()

    info = extract_header_info(text)
    tech_stack = extract_tech_stack(text)
    pricing_model = extract_pricing_model(text)
    features = extract_features(text, competitor_dir)
    language = detect_language(tech_stack, text)

    # Extract name from first heading
    name_match = re.search(r'^#\s+(.+?)(?:\s*[-–—])', text, re.MULTILINE)
    if not name_match:
        name_match = re.search(r'^#\s+(.+)', text, re.MULTILINE)
    name = name_match.group(1).strip() if name_match else competitor_slug.replace("-", " ").title()

    return {
        "name": name,
        "product_line": product_line,
        "slug": competitor_slug,
        "url": info.get("url"),
        "license": info.get("license"),
        "tech_stack": tech_stack,
        "language": language,
        "pricing_model": pricing_model,
        "github_url": info.get("github_url"),
        "github_stars": info.get("github_stars"),
        "analysis_path": os.path.relpath(merged_path, BASE_DIR),
        "features": features,
    }


def main():
    if not os.path.exists(DB_PATH):
        print(f"Database not found: {DB_PATH}")
        return

    conn = sqlite3.connect(DB_PATH)
    conn.execute("PRAGMA foreign_keys=ON")

    total_competitors = 0
    total_features = 0

    for dir_name, product_line in PRODUCT_LINES.items():
        product_dir = os.path.join(BASE_DIR, dir_name)
        if not os.path.isdir(product_dir):
            continue

        print(f"\n=== {product_line} ===")

        for competitor_slug in sorted(os.listdir(product_dir)):
            competitor_dir = os.path.join(product_dir, competitor_slug)
            if not os.path.isdir(competitor_dir):
                continue
            if competitor_slug in ("concurrentie-analyse.md",):
                continue

            # Check if already exists
            existing = conn.execute(
                "SELECT id FROM competitors WHERE slug = ?",
                (competitor_slug,)
            ).fetchone()
            if existing:
                continue

            result = process_competitor(product_line, competitor_slug, competitor_dir)
            if not result:
                print(f"  SKIP (no analysis): {competitor_slug}")
                continue

            conn.execute("""INSERT INTO competitors
                (name, product_line, slug, url, license, tech_stack, language,
                 pricing_model, github_url, github_stars, analysis_path, specs_count)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)""",
                (result["name"], result["product_line"], result["slug"],
                 result["url"], result["license"], result["tech_stack"],
                 result["language"], result["pricing_model"],
                 result["github_url"], result["github_stars"],
                 result["analysis_path"], len(result["features"])))

            competitor_id = conn.execute("SELECT last_insert_rowid()").fetchone()[0]

            for feat in result["features"]:
                conn.execute("""INSERT INTO competitor_features
                    (competitor_id, feature_name, category, description, spec_path)
                    VALUES (?, ?, ?, ?, ?)""",
                    (competitor_id, feat["name"], feat.get("category"),
                     feat.get("description"), feat.get("spec_path")))
                total_features += 1

            total_competitors += 1
            print(f"  {competitor_slug}: {result['name']} ({len(result['features'])} features)")

    conn.commit()

    print(f"\nResults:")
    print(f"  Competitors: {total_competitors}")
    print(f"  Features: {total_features}")

    # Summary by product line
    for row in conn.execute(
        "SELECT product_line, COUNT(*) FROM competitors GROUP BY product_line ORDER BY product_line"
    ):
        print(f"  {row[0]}: {row[1]} competitors")

    conn.close()


if __name__ == "__main__":
    main()
