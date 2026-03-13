---
status: draft
source: competitive-analysis
competitor: open-klant
analyzed_date: 2026-03-13
---

# Digitale Adressen (Digital Addresses) -- Open Klant

## Purpose

A DigitaalAdres stores a digital contact address (email, phone number, or other) for a Partij or a Betrokkene. Supports default addresses per type, verification dates, and machine-readable references.

- **Product**: Open Klant
- **Category**: Contact Information Management
- **Relevance to Pipelinq**: Multi-channel digital contact management for clients.

## Data Model

| Field | Type | Description |
|-------|------|-------------|
| uuid | UUIDField | |
| partij | FK -> Partij (nullable) | The party who owns this address |
| betrokkene | FK -> Betrokkene (nullable) | Contact-specific address |
| soort_digitaal_adres | CharField(255) | `email` / `telefoonnummer` / `overig` |
| adres | CharField(80) | The actual address value |
| omschrijving | CharField(40) | Description |
| is_standaard_adres | BooleanField (default False) | Default for this type per party |
| referentie | SlugField | Machine-readable tag for unique identification |
| verificatie_datum | DateField (nullable) | When the address was verified |

### Database Constraints

1. `unique_default_per_partij_and_soort`: Only one `is_standaard_adres=True` per (partij, soort_digitaal_adres)
2. `unique_referentie_per_partij_and_soort`: Unique (partij, referentie, soort_digitaal_adres) when referentie is non-empty and partij is set

### Save Logic

When `is_standaard_adres=True`: All other DigitaalAdres records with the same (soort_digitaal_adres, partij) are set to `is_standaard_adres=False`.

### Validation

- Email addresses validated with Django's EmailValidator
- Phone numbers validated with custom phone number validator
- `overig` type has no format validation

## API Endpoints

| Method | Path | Description | Auth |
|--------|------|-------------|------|
| GET/POST | `/klantinteracties/api/v1/digitaleadressen/` | List/Create | Token |
| GET/PUT/PATCH/DELETE | `/klantinteracties/api/v1/digitaleadressen/{uuid}/` | Detail CRUD | Token |

### Filters

- `partij__uuid`, `partij__url`
- `betrokkene__uuid`, `betrokkene__url`
- `soort_digitaal_adres`
- `adres`
- `is_standaard_adres`
- `expand` (supports detail expand)

## Pipelinq Comparison

| Aspect | Open Klant | Pipelinq |
|--------|-----------|----------|
| Address types | email/telefoonnummer/overig as separate entities | Inline fields on client object |
| Default addresses | is_standaard_adres per type | Not available |
| Verification | verificatie_datum | Not available |
| Multiple per type | Unlimited per party | Typically single email/phone |
| Machine reference | referentie slug field | Not available |
| Contact-specific | Can belong to Betrokkene (contact-specific address) | Not available |

**Already in Pipelinq**: Basic email/phone storage
**Not yet in Pipelinq**: Multi-address per type, default address designation, verification tracking, contact-specific addresses, machine-readable references
