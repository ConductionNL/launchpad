# Open Formulieren — Prefill (BRP, KvK, Objects API)

## Overview

Prefill plugins automatically populate form fields with known data based on the authenticated user's identity. This reduces manual data entry for citizens and improves data quality by using authoritative sources.

## Available Prefill Sources

### 1. Haal Centraal BRP Personen Bevragen

**Data source:** Basisregistratie Personen (National Population Register)
**Triggered by:** DigiD authentication (provides BSN)
**API version:** v2.0 (recommended for new installations)

**Available data:**
- Full name (voornamen, voorvoegsel, geslachtsnaam)
- Date of birth
- Address (street, house number, postal code, city)
- Gender
- Nationality
- Partner information (since recent versions)
- Children of the authenticated user

**Configuration:**
- Select BRP Personen Bevragen API service
- Choose correct API version (v1.3 or v2.0)
- Configure gateway headers if applicable:
  - `x-doelbinding` — Purpose binding header
  - `x-verwerking` — Processing header
- Map BRP attributes to form fields

### 2. StUF-BG (Legacy BRP Access)

**Data source:** Same BRP data via StUF-BG v3.1 SOAP interface
**Triggered by:** DigiD authentication (provides BSN)

**Available data:** Same as Haal Centraal but accessed via SOAP/XML
**Status:** Legacy — municipalities migrating to Haal Centraal REST API

### 3. KvK / Handelsregister (Chamber of Commerce)

**Data source:** Haal Centraal HR API (Handelsregister)
**Triggered by:** eHerkenning authentication (provides KvK number)

**Available data:**
- Company name (handelsnaam)
- Legal form
- Business address (vestigingsadres)
- Registration number
- RSIN

**Implementation:** Available as an extension plugin for the Haal Centraal HR API.

### 4. Objects API (Record-Based Prefill)

**Data source:** Objects API (custom objects/records)
**Available since:** v3.0
**Triggered by:** Object reference in the form URL

**Use case:** When a citizen has existing records (products, permits, subscriptions) stored in the Objects API, those records can be used to prefill the form. For example:
- Renewing a permit → prefills existing permit data
- Updating a registration → prefills current registration data

**Features:**
- References existing object by URL
- Maps object properties to form variables
- Can update the existing object during registration (instead of creating new)

### 5. Custom Prefill Plugins

Third-party extensions can implement additional prefill sources by following the plugin interface. Each plugin is responsible for fetching relevant data from its specific backend.

## Prefill Plugin Interface

```python
# Each plugin must implement:
# - Declare which attributes/fields it can provide
# - Fetch data based on the authenticated user's identity
# - Return a mapping of attribute -> value
```

## Field-Level Prefill Configuration

In the form builder, each field can be configured with:
- **Prefill plugin** — Which data source to use
- **Prefill attribute** — Which specific attribute from the source
- **Default value** — Fallback if prefill fails
- **Read-only** — Whether prefilled value can be edited

## Comparison with Procest

| Feature | Open Formulieren | Procest |
|---------|-----------------|---------|
| BRP prefill (Haal Centraal) | Yes | No |
| BRP prefill (StUF-BG) | Yes | No |
| KvK prefill | Yes | No |
| Objects API prefill | Yes (v3.0+) | Via OpenRegister |
| Custom prefill plugins | Yes | No |
| Citizen-facing prefill | Yes | N/A (internal tool) |
| Case data reuse | No | Yes (ZGW Zaakeigenschappen) |

### Analysis

Prefill is tightly coupled with citizen-facing authentication — it only makes sense when a citizen is logged in via DigiD or eHerkenning. Since Procest is an internal case management tool, it does not need BRP/KvK prefill in the same way. However, if Procest adds a citizen-facing intake component in the future, prefill capabilities would be essential for usability.

For now, the recommended approach is to use Open Formulieren for citizen-facing intake with prefill, and let Procest handle the resulting cases.
