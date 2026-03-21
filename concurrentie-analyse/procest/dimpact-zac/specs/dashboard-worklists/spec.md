---
status: draft
source: competitive-analysis
competitor: dimpact-zac
analyzed_date: 2026-03-14
---

# Dashboard and Worklists -- Dimpact ZAC

## Purpose
Competitive analysis spec documenting how Dimpact ZAC implements dashboards and work queues.
- **Product**: Dimpact ZAC
- **Category**: User Interface / Productivity
- **Relevance to Procest**: Dashboards and worklists are the primary interface for daily work

## Architecture Overview
ZAC's main page is a configurable dashboard with cards. Worklists provide filtered views of cases and tasks. All data comes from Solr search.

Frontend: Angular components with Material UI
Backend: Search REST service + Signalering REST service

## Data Model

### Dashboard Cards
| Card | Description |
|------|-------------|
| Zaak zoeken | Quick case search |
| Taak zoeken | Quick task search |
| Zaken card | Cases overview |
| Taken card | Tasks overview |
| Informatieobjecten card | Documents overview |
| Zaak waarschuwingen card | Case deadline warnings |

### Worklist Types (Werklijst)
| Werklijst | Route | Description |
|-----------|-------|-------------|
| MIJN_ZAKEN | /zaken/mijn | Cases assigned to me |
| WERKVOORRAAD_ZAKEN | /zaken/werkvoorraad | Unassigned cases in my groups |
| AFGEHANDELDE_ZAKEN | /zaken/afgehandeld | Completed cases |
| MIJN_TAKEN | /taken/mijn | Tasks assigned to me |
| WERKVOORRAAD_TAKEN | /taken/werkvoorraad | Unassigned tasks in my groups |

### Dynamic Table System
ZAC uses a reusable `DynamicTable` component with:
- Configurable columns per worklist
- Saved search queries (zoekopdrachten)
- Table settings persistence (column visibility, sort order)
- Paginator
- Export button (CSV)

### Gebruikersvoorkeuren (User Preferences)
- Saved search queries per worklist
- Table column configuration
- Sort preferences

## Business Logic

### Worklist Data Loading
1. Angular resolver loads table configuration
2. Component sends search request to `/rest/zoeken/list`
3. SearchService queries Solr with:
   - Type filter (ZAAK or TAAK)
   - User/group filter (mijn vs werkvoorraad)
   - Facet filters
   - Date range filters
   - Sort and pagination
4. Results filtered through OPA policies
5. Returns paginated, faceted results

### Case Warning Indicators
Cases display warnings for:
- **STREEF_DATUM** -- approaching target date
- **FATALE_DATUM** -- approaching hard deadline
- **OPSCHORTING** -- suspended
- **VERLENGD** -- extended
- **HEROPEND** -- reopened
- **HOOFDZAAK** -- is parent case
- **DEELZAAK** -- is sub-case
- **BESLUIT** -- has decisions

### Saved Searches (Zoekopdrachten)
- Users can save filter combinations as named searches
- Searches stored per-worklist in database
- Quick-apply saved search from dropdown
- Save dialog for new/update searches

### CSV Export
- Export worklist to CSV
- Requires `werklijst.zakenTakenExporteren` permission (beheerder role)

## Requirements (as observed)

1. Dashboard is the landing page with configurable cards
2. Five worklist views cover all daily work patterns
3. Worklists use Solr-backed search with faceted filtering
4. User preferences persist table configuration
5. Saved searches allow quick filter application
6. CSV export available for administrators
7. Warning indicators highlight cases needing attention

## Comparison Notes
- ZAC's worklist system is mature with saved searches and configurable columns
- The Solr-backed approach enables fast filtering on large datasets
- Procest's dashboard could adopt similar card-based layout
- The saved search feature is valuable for recurring filter patterns
- Warning indicators are useful for deadline management
