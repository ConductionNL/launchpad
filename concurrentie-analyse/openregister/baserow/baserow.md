# Baserow — Competitor Analysis

## Overview

- **Website:** https://baserow.io/
- **Open Source:** Yes (MIT)
- **Self-Hosted:** Yes
- **Summary:** Open-source no-code database and app builder, Airtable alternative

## Codebase

https://github.com/baserow/baserow (previously on GitLab at https://gitlab.com/baserow/baserow)

## Business Model

Open-core model with MIT license for the core. Revenue from hosted cloud plans (per-user pricing) and self-hosted Premium/Enterprise licenses. Self-hosted deployments have no row or storage limits. Enterprise features (SSO, audit logs, advanced RBAC) require paid licenses. Trusted by 150,000+ users. GDPR, HIPAA, and SOC 2 Type II compliant.

## Target Market

Business and IT teams in security-sensitive industries including Defense, Aerospace, Healthcare, Research, and Manufacturing. Organizations needing a self-hosted Airtable alternative with enterprise security compliance. Teams wanting no-code database capabilities with full data sovereignty.

## Pricing

- **Free (self-hosted):** Open-source core with no row/storage limits
- **Free (cloud):** Up to 3,000 rows per workspace
- **Premium (cloud):** $5/user/month — more rows, premium views
- **Advanced (cloud):** $20/user/month — 250,000 rows/workspace, advanced features
- **Enterprise (self-hosted):** Custom pricing — SSO, audit logs, advanced RBAC

## Key Features

- Spreadsheet-style database with Grid, Kanban, Gallery, Calendar, Form, and Survey views
- Built-in AI Assistant for natural language database creation and workflow building
- Real-time collaboration with multiple simultaneous editors
- Automation workflows with triggers and actions
- Application builder for creating custom internal tools
- Dashboard builder for data visualization
- REST API for all operations
- Plugin architecture for extensibility
- Import/export from CSV, JSON, XML
- 30+ field types including file attachments, links, lookups, formulas

## Feature Comparison with OpenRegister

| Feature | Baserow | OpenRegister |
|---------|-------|--------------|
| JSON Schema data modeling | No (custom schema) | Yes |
| Auto-generated REST APIs | Yes | Yes |
| Full-text search | Partial (basic) | Yes |
| Faceted search | No | Yes |
| RBAC | Yes | Yes |
| Audit trails | Yes (Enterprise) | Yes |
| Multi-tenancy | Yes (workspaces) | Yes |
| Webhooks / Events | Yes | Yes |
| AI / Vector embeddings | Partial (AI assistant) | Yes |
| Semantic search | No | Yes |
| Object relations | Yes (link rows) | Yes |
| Soft deletes | Yes (trash) | Yes |
| Time-travel queries | No | Yes |
| CalDAV integration | No | Yes |
| JSON-LD / Linked Data | No | Yes |
| Nextcloud integration | No | Native |
| NLGov API compliance | No | Yes |

## Strengths

- MIT license is very permissive, allowing embedding in proprietary products without restrictions
- Strong enterprise compliance (GDPR, HIPAA, SOC 2 Type II) makes it suitable for regulated industries
- Built-in AI Assistant and application builder go beyond simple data management into full app creation

## Weaknesses

- No JSON Schema or linked data support — uses proprietary data modeling rather than open standards
- No semantic search, vector embeddings, or time-travel query capabilities
- No government-specific API compliance or Nextcloud integration

## Notes

Strong European company (based in the Netherlands). Active community with 150k+ users. Recently migrated from GitLab to GitHub. The MIT license and European origin make it an interesting comparison for Dutch government contexts, though it lacks the specific NLGov compliance features of OpenRegister.
