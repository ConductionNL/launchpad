---
status: draft
source: competitive-analysis
competitor: open-klant
analyzed_date: 2026-03-13
---

# Categorieen (Party Categories) -- Open Klant

## Purpose

EXPERIMENTAL feature for categorizing parties. A Categorie has a name, and CategorieRelatie links a Partij to a Categorie with optional begin/end dates.

- **Product**: Open Klant
- **Category**: Categorization / Tagging
- **Relevance to Pipelinq**: Client segmentation and categorization.

## Data Model

### Categorie

| Field | Type | Description |
|-------|------|-------------|
| uuid | UUIDField | |
| naam | CharField(80) | Category name |

### CategorieRelatie

| Field | Type | Description |
|-------|------|-------------|
| uuid | UUIDField | |
| partij | FK -> Partij (nullable) | |
| categorie | FK -> Categorie (nullable) | |
| begin_datum | DateField (nullable) | Start date of categorization |
| eind_datum | DateField (nullable) | End date of categorization |

## API Endpoints

| Method | Path | Description | Auth |
|--------|------|-------------|------|
| `/categorieen/` | Categorie | Full CRUD (EXPERIMENTAL) | Token |
| `/categorie-relaties/` | CategorieRelatie | Full CRUD (EXPERIMENTAL) | Token |

### CategorieRelatie Filters

- `partij__uuid`, `partij__url`, `partij__nummer`
- `categorie__uuid`, `categorie__url`, `categorie__naam`
- `begin_datum`, `eind_datum`

### Default begin_datum

On create, if `begin_datum` is not provided, it defaults to today's date.

## Pipelinq Comparison

**Already in Pipelinq**: Tags/labels may be possible via OpenRegister custom fields
**Not yet in Pipelinq**: Formal category system with date ranges (time-bounded categorization)
