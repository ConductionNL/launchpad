#!/usr/bin/env python3
"""Pull feature requests from GitHub issue trackers of open source competitors."""

import json
import sqlite3
import sys
import urllib.request
import time

DB_PATH = sys.argv[1] if len(sys.argv) > 1 else "concurrentie-analyse/intelligence.db"

# Labels that indicate feature requests
FEATURE_LABELS = {"enhancement", "feature", "feature-request", "feature request",
                  "new feature", "improvement", "idea", "suggestion", "rfe"}

# GitHub API rate limit: 60 requests/hour unauthenticated
MAX_REPOS = 20  # Process top repos per sync to stay within limits
ISSUES_PER_REPO = 30  # Top feature requests per repo


def github_get(url):
    """Make a GitHub API request with proper headers."""
    req = urllib.request.Request(url, headers={
        "User-Agent": "ConductionIntelligence/1.0",
        "Accept": "application/vnd.github.v3+json"
    })
    try:
        with urllib.request.urlopen(req, timeout=30) as resp:
            remaining = resp.headers.get("X-RateLimit-Remaining", "?")
            if remaining != "?" and int(remaining) < 5:
                print(f"Rate limit low ({remaining}), stopping early", file=sys.stderr)
                return None
            return json.loads(resp.read().decode())
    except Exception as e:
        print(f"GitHub API error for {url}: {e}", file=sys.stderr)
        return None


def extract_repo_path(github_url):
    """Extract owner/repo from a GitHub URL."""
    if not github_url:
        return None
    url = github_url.rstrip("/")
    parts = url.replace("https://github.com/", "").split("/")
    if len(parts) >= 2:
        return f"{parts[0]}/{parts[1]}"
    return None


def get_feature_issues(repo_path):
    """Fetch top feature request issues sorted by reactions."""
    # Try common feature request labels
    for label in ["enhancement", "feature-request", "feature", "feature request"]:
        url = (f"https://api.github.com/repos/{repo_path}/issues?"
               f"labels={urllib.request.quote(label)}&state=all&sort=reactions-+1"
               f"&direction=desc&per_page={ISSUES_PER_REPO}")
        data = github_get(url)
        if data is None:
            return None  # Rate limited
        if data:
            return data

    # Fallback: search issues with feature keywords
    url = (f"https://api.github.com/search/issues?"
           f"q=repo:{repo_path}+is:issue+label:enhancement&sort=reactions-+1"
           f"&order=desc&per_page={ISSUES_PER_REPO}")
    data = github_get(url)
    if data and "items" in data:
        return data["items"]

    return []


def main():
    conn = sqlite3.connect(DB_PATH)

    # Get all open source competitors with GitHub URLs
    competitors = conn.execute("""
        SELECT c.id, c.name, c.github_url, c.product_line,
               sc.slug as category_slug
        FROM competitors c
        LEFT JOIN software_categories sc ON sc.conduction_product = c.product_line
            OR sc.slug = c.product_line
        WHERE c.github_url IS NOT NULL
        ORDER BY (SELECT COUNT(*) FROM competitor_features cf WHERE cf.competitor_id = c.id) DESC
        LIMIT ?
    """, (MAX_REPOS,)).fetchall()

    new_count = 0
    total_issues = 0
    errors = []

    for comp_id, comp_name, github_url, product_line, cat_slug in competitors:
        repo_path = extract_repo_path(github_url)
        if not repo_path:
            continue

        issues = get_feature_issues(repo_path)
        if issues is None:
            errors.append(f"Rate limited at {comp_name}")
            break  # Stop processing, we're rate limited

        for issue in issues:
            if isinstance(issue, dict) and not issue.get("pull_request"):
                total_issues += 1
                title = issue.get("title", "")
                body = (issue.get("body") or "")[:300]
                issue_url = issue.get("html_url", "")
                reactions = issue.get("reactions", {}).get("+1", 0)
                labels = [l.get("name", "") for l in issue.get("labels", [])]

                # Determine if this is a feature request
                is_feature = any(l.lower() in FEATURE_LABELS for l in labels)
                if not is_feature:
                    continue

                # Try to map to category features
                categories_to_check = []
                if cat_slug:
                    categories_to_check.append(cat_slug)

                # Also check product_line mapping
                product_cats = conn.execute(
                    "SELECT slug FROM software_categories WHERE conduction_product = ?",
                    (product_line,)
                ).fetchall()
                for (pc,) in product_cats:
                    if pc not in categories_to_check:
                        categories_to_check.append(pc)

                if not categories_to_check:
                    continue

                for check_cat in categories_to_check:
                    features = conn.execute(
                        "SELECT feature_slug FROM category_features WHERE category_slug = ?",
                        (check_cat,)
                    ).fetchall()

                    for (feat_slug,) in features:
                        cur = conn.execute("""
                            INSERT OR IGNORE INTO feature_sources
                            (category_slug, feature_slug, source_type, source_url,
                             source_label, source_detail, competitor_id, sentiment)
                            VALUES (?, ?, 'github-issue', ?, ?, ?, ?, 'positive')
                        """, (check_cat, feat_slug, issue_url,
                              f"GitHub issue: {comp_name}",
                              f"{title} (+1:{reactions}) {body[:100]}",
                              comp_id))
                        if cur.rowcount > 0:
                            new_count += 1

        # Small delay between repos to be nice to GitHub API
        time.sleep(1)

    conn.commit()
    conn.close()

    print(json.dumps({
        "records": total_issues,
        "new": new_count,
        "repos_checked": len(competitors),
        "errors": errors
    }))


if __name__ == "__main__":
    main()
