# Prefill Plugins

## What Open Forms Does

Prefill plugins automatically populate form fields with data from authoritative sources based on the authenticated user's identity.

### Plugin Architecture
- `BasePlugin` with `get_available_attributes()` and `get_prefill_values(submission, attributes, identifier_role)`
- `requires_auth`: specifies which `AuthAttribute` (bsn, kvk) must be present
- `requires_auth_plugin`: some prefills need specific auth plugins
- `for_components`: limits which FormIO component types the plugin works with

### Prefill Plugins

| Plugin | Source | Auth Required | Data |
|--------|--------|---------------|------|
| `haalcentraal_brp` | Haal Centraal BRP API | BSN | Name, address, birth date, nationality, gender |
| `stufbg` | StUF-BG (SOAP) | BSN | Same as above via legacy protocol |
| `kvk` | KvK API | KvK | Company name, address, trade names, legal form |
| `objects_api` | Objects API | Varies | Custom objects by reference |
| `suwinet` | Suwinet (social services) | BSN | Income, benefits, social security data |
| `family_members` | Haal Centraal | BSN | Partners, children data |
| `customer_interactions` | Klantinteracties API | BSN/KvK | Customer contact info, communication preferences |
| `eidas` | eIDAS attributes | Pseudo ID | EU cross-border identity data |
| `yivi` | Yivi/IRMA | Yivi auth | Self-sovereign identity attributes |
| `demo` | Static | None | Test data |

### How Prefill Works
1. Form variable has `prefill_plugin` and `prefill_attribute` configured
2. On submission start, system collects all variables needing prefill
3. Groups variables by plugin, calls `get_prefill_values()` in bulk
4. Returned values populate `SubmissionValueVariable` with source=`prefill`
5. `identifier_role` supports `main` (submitter) and `authorizee` (on behalf of)

### Objects API Prefill
- Uses `initial_data_reference` to fetch an existing object
- Maps object properties to form variables
- Supports nested JSON path mapping

## Already in Procest

- Basic data retrieval from OpenRegister objects
- n8n workflows can fetch external data

## Not Yet in Procest

- **Haal Centraal BRP prefill** -- No automatic citizen data lookup by BSN
- **KvK prefill** -- No automatic company data lookup by KvK number
- **StUF-BG prefill** -- No legacy SOAP-based person data lookup
- **Suwinet prefill** -- No social services data integration
- **Plugin-based prefill architecture** -- No pluggable prefill system per form variable
- **Attribute-level prefill configuration** -- No per-variable prefill source mapping
- **Family members prefill** -- No automatic partner/children data loading
- **Objects API prefill** -- No pre-population from existing Objects API records
- **Customer interactions prefill** -- No KlantInteracties API integration
- **Identifier role support** -- No "main" vs "authorizee" prefill distinction
