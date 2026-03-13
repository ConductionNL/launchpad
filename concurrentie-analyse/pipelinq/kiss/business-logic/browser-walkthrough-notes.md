# Browser Walkthrough Notes

## Existing Screenshots
14 screenshots already captured from `dev.kiss-demo.nl` (live KISS demo instance), covering:
- Contactverzoek form management (admin CRUD)
- Dynamic form builder (VragenSets with open questions, dropdowns, answer options)
- Delete confirmation dialogs
- Full admin navigation (Skills, Links, Gespreksresultaten, Formulieren contactverzoek)

## Docker Setup Assessment
**Decision: Skip local Docker setup**

KISS requires a complex infrastructure stack that is not practical to run locally for a competitive analysis:
- OpenKlant 1/2 (customer registration API)
- OpenZaak (case management, ZGW APIs)
- Haal Centraal BRP (citizen lookup - requires government credentials)
- KvK API (business registry - requires API key)
- Elasticsearch + Enterprise Search (App Search)
- OIDC identity provider (Azure AD/Keycloak)
- PostgreSQL (BFF database)
- Objecten API + Objecttypen API

The KISS-frontend repo does include Docker Compose files, but they assume pre-configured external services. Without government API credentials (BRP, KvK), most of the interesting functionality would not work.

The existing screenshots from `dev.kiss-demo.nl` plus the detailed source code analysis provide sufficient insight for the competitive analysis.

## Additional Screenshot Sources
- KISS ReadTheDocs: https://kiss-frontend.readthedocs.io/
- KISS GitHub: https://github.com/Klantinteractie-Servicesysteem/KISS-frontend
- Decision records contain UI mockups and flow descriptions
