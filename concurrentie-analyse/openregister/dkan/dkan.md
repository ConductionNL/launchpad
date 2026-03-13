# DKAN — Competitor Analysis

## Overview

- **Website:** https://getdkan.org/
- **Open Source:** Yes (GPL-2.0+)
- **Self-Hosted:** Yes
- **Summary:** Drupal-based open data portal with harvesting and visualization

## Codebase

https://github.com/GetDKAN/dkan

## Business Model

Fully open-source project (GPL-2.0+) with no commercial licensing. Publicly funded and community-owned — no licensing fees or subscription costs. Maintained by CivicActions, a US-based civic tech consultancy. Revenue in the ecosystem comes from government implementation contracts, custom development, and support services from CivicActions and other Drupal agencies.

## Target Market

US federal, state, and local government agencies publishing open data. Nonprofits and universities sharing public datasets. Organizations already running Drupal that want to add open data portal capabilities. Government agencies wanting an alternative to CKAN with Drupal's content management capabilities.

## Pricing

- **Self-hosted:** Completely free, GPL-2.0+ license
- **No official cloud offering**
- Implementation costs through CivicActions or other Drupal agencies
- Lower implementation costs than CKAN for organizations already on Drupal

## Key Features

- Drupal module for adding open data portal capabilities to Drupal sites
- JSON-based data objects with JSON Schema enforcement
- DCAT-US metadata standard compliance
- Dataset harvesting from Socrata and other data catalogs
- Datastore for CSV storage with SQL query endpoint
- Decoupled React frontend option
- RESTful API for datasets and resources
- Data visualization and charting tools
- User management leveraging Drupal's permission system
- Content management alongside data catalog (Drupal CMS)
- Metadata search and faceted filtering

## Feature Comparison with OpenRegister

| Feature | DKAN | OpenRegister |
|---------|-------|--------------|
| JSON Schema data modeling | Yes | Yes |
| Auto-generated REST APIs | Yes | Yes |
| Full-text search | Yes | Yes |
| Faceted search | Yes | Yes |
| RBAC | Yes (Drupal) | Yes |
| Audit trails | Yes (Drupal) | Yes |
| Multi-tenancy | Partial (Drupal multi-site) | Yes |
| Webhooks / Events | Partial (Drupal hooks) | Yes |
| AI / Vector embeddings | No | Yes |
| Semantic search | No | Yes |
| Object relations | Partial | Yes |
| Soft deletes | Partial (Drupal) | Yes |
| Time-travel queries | No | Yes |
| CalDAV integration | No | Yes |
| JSON-LD / Linked Data | Yes (DCAT) | Yes |
| Nextcloud integration | No | Native |
| NLGov API compliance | Partial (DCAT-US) | Yes |

## Strengths

- Built on Drupal — combines open data portal with full CMS capabilities for content + data sites
- JSON Schema enforcement for data objects — one of few competitors with actual JSON Schema support
- DCAT metadata compliance and data harvesting from external catalogs

## Weaknesses

- Tied to Drupal ecosystem — requires Drupal expertise and infrastructure
- Primarily US-focused (DCAT-US) — less aligned with European/Dutch government standards
- Smaller community and fewer deployments compared to CKAN

## Notes

Maintained by CivicActions for 20+ years. Completely rewritten for Drupal 8/9/10 in 2020. The JSON Schema support is notable — DKAN is one of the few competitors that actually uses JSON Schema for data validation. However, it is specifically a data catalog/portal rather than a general-purpose data register. The Drupal dependency is both a strength (leveraging Drupal's ecosystem) and a weakness (requiring Drupal expertise).
