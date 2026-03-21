# ZAC Solution Architecture

Source: https://github.com/infonl/dimpact-zaakafhandelcomponent/blob/main/docs/solution-architecture/README.md

## Main Characteristics

- ZAC is deployed as one **WildFly** runtime container serving both backend and frontend (single process)
- Uses **Apache Solr** search engine (separate runtime)
- Requires **PostgreSQL** relational database
- Uses **Open Policy Agent (OPA)** for security policies (separate runtime)
- Does NOT require an external file system (NFS/SMB)

## Architecture Approach

Documented using the **C4 Model** with **Mermaid** diagrams.

## Common Ground Principles

| Principle | Implication |
|-----------|------------|
| Component based | ZAC has clear purpose and bounded context; unrelated functionality should be moved outside |
| Open source | EUPL 1.2 license; way of working, docs, stories all public |
| Data at source | Data kept at source; ZAC caches some Open Zaak data in Solr for performance but treats Open Zaak as single source of truth |
| Standards | Runs on Kubernetes with Helm Chart; NOT compliant with NLX or Haven; NO public API (BFF only) |

## Key Architectural Decisions

1. **No public API** — The ZAC backend REST API is exclusively a "backend for frontend" serving only the Angular frontend
2. **Single instance only** — Cannot scale horizontally (session management + notification handling constraints)
3. **Embedded process engine** — Flowable runs within the WildFly container, not as a separate service
4. **Event-driven updates** — ZAC subscribes to Open Notificaties for zaak/document changes
5. **Solr as read-optimized cache** — Indexes data from both Open Zaak and internal ZAC state
