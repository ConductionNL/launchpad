# KISS Architecture Documentation

Source: https://github.com/Klantinteractie-Servicesysteem/KISS-frontend/blob/main/docs/architectuur/Architectuur.md

## Stack

- **Frontend:** Vue.js (TypeScript, Vite)
- **Backend for Frontend (BFF):** .NET (C#)
- **Database:** PostgreSQL
- **Search:** Elasticsearch + Enterprise Search (App Search)
- **Authentication:** OIDC (Azure AD / EntraID compatible)
- **Deployment:** Docker, Kubernetes (Helm charts)
- **License:** EUPL-1.2

## Architecture Overview

KISS is a "Backend for Frontend" (BFF) pattern application. The Vue frontend talks exclusively to the .NET BFF, which proxies and orchestrates calls to external APIs.

### Architecture with Open Klant 2.x

Components:
- KISS Frontend (Vue) -> KISS BFF (.NET)
- KISS BFF -> Open Klant 2.x (Klantinteracties API)
- KISS BFF -> Open Zaak (ZGW APIs: Zaken, Catalogi, Documenten)
- KISS BFF -> Objecten API (Afdelingen, Groepen, Medewerkers, VAC, PDC)
- KISS BFF -> Haal Centraal BRP (person queries)
- KISS BFF -> KvK API (business queries)
- KISS BFF -> Elasticsearch (search index)
- KISS-Elastic-Sync -> Elasticsearch (syncs Objects API data into search index)

### Architecture with e-Suite (Dimpact municipalities)

For Dimpact municipalities using e-Suite, KISS can connect to:
- e-Suite Contactmomenten API (OpenKlant v0.5-pre compatible)
- e-Suite Klanten API
- e-Suite Zaaksysteem

## Multiple Registers

KISS supports connecting to multiple backend register sets simultaneously:
- A "default" register for contact moments not linked to a case
- Per-zaaksysteem register sets with their own Contactmomenten/Klanten registers
- Each register set can be either `OpenKlant2` or `OpenKlant1` (e-Suite) type

## API Integrations

### National Base Registries
- KvK APIs (https://developers.kvk.nl/documentation/zoeken-api)
- Haal Centraal BRP Personen bevragen

### VNG / Common Ground Standards
- ZGW Zaken API
- ZGW Catalogi API
- ZGW Documenten API
- Klantinteracties API (Open Klant 2.x)
- Contactmomenten API (Open Klant 1.x / e-Suite)
- Klanten API (Open Klant 1.x / e-Suite)
- Objecten API
- Objecttypen API

### Object Types Used (via Objecten API)
- Afdeling (department)
- Groep (group)
- Medewerker (employee)
- VAC (Vraag-Antwoord Combinatie)
- PDC (Kennisartikel / product page)
- InterneTaak (contact request, only for OpenKlant1 mode)

## Source Code Structure (KISS-frontend)

### Features (src/features/)
- `Kanalen` — channel management
- `bedrijf` — business/company lookup (KvK)
- `beheer` — admin/management
- `contact` — contact moment registration
- `feedback` — feedback on knowledge articles
- `klant` — customer lookup/management
- `links` — configurable links
- `login` — OIDC login
- `persoon` — person lookup (BRP)
- `search` — unified search (Elasticsearch)
- `shared` — shared components
- `werkbericht` — news/work instructions
- `zaaksysteem` — case system integration

### Other Repos
- `KISS-Elastic-Sync` — syncs Objects API data to Elasticsearch
- `pdc-component` — OAS and testdata for SDG invoervoorziening
- `Openpub` — OpenPub standard support
- `OpenZaak-Charts` — Helm charts for Open Zaak
