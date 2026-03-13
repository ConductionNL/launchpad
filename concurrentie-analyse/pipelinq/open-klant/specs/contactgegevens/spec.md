---
status: draft
source: competitive-analysis
competitor: open-klant
analyzed_date: 2026-03-13
---

# Contactgegevens API (Contact Details) -- Open Klant

## Purpose

A separate, simpler API component for basic person and organisation contact details. This is NOT based on a VNG standard -- it is a Maykin-proprietary API. It stores richer person data (geslacht, geboortedatum, voornamen) and organisation data (handelsnaam, oprichtingsdatum) with full address support.

- **Product**: Open Klant
- **Category**: Contact Data Storage
- **Relevance to Pipelinq**: Simpler alternative to Klantinteracties for basic contact data.

## Data Model

### Persoon

| Field | Type | Description |
|-------|------|-------------|
| uuid | UUIDField | |
| geboortedatum | DateField | Date of birth |
| overlijdensdatum | DateField (nullable) | Date of death |
| geslachtsnaam | CharField(200) | Family name |
| geslacht | CharField(1) | M/V/O |
| voorvoegsel | CharField(10) | Name prefix |
| voornamen | CharField(200) | First names |
| adres_* | AdresMixin fields | Full address with nummeraanduiding_id, straat, huisnummer, postcode, stad, land |

### Organisatie

| Field | Type | Description |
|-------|------|-------------|
| uuid | UUIDField | |
| handelsnaam | CharField(255) | Trade name |
| oprichtingsdatum | DateField (nullable) | Founding date |
| opheffingsdatum | DateField (nullable) | Dissolution date |
| adres_* | AdresMixin fields | Full address |

## API Endpoints (at `/contactgegevens/api/v1/`)

| Method | Path | Description | Auth |
|--------|------|-------------|------|
| GET/POST | `/persoon/` | List/Create persons | Token |
| GET/PUT/PATCH/DELETE | `/persoon/{uuid}/` | Detail CRUD | Token |
| GET/POST | `/organisatie/` | List/Create orgs | Token |
| GET/PUT/PATCH/DELETE | `/organisatie/{uuid}/` | Detail CRUD | Token |

## Pipelinq Comparison

This is the simpler of Open Klant's two APIs. It stores basic contact data without the complex interaction tracking of the Klantinteracties API.

**Already in Pipelinq**: Basic person/org data storage via OpenRegister
**Not yet in Pipelinq**: Structured person fields (geslacht, geboortedatum), organisation lifecycle dates
