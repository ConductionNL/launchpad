# ZAC Solr Architecture

Source: https://github.com/infonl/dimpact-zaakafhandelcomponent/blob/main/docs/solution-architecture/solrArchitecture.md

## Purpose

Solr search engine for fast retrieval and search of zaak-related information.
Used in multiple places, notably the "werkvoorraad" (workload) screens.

## Index Content

Contains both:
- External data from Open Zaak (zaak data)
- ZAC-internal data (task data)

## Startup

- ZAC checks for Solr index on startup
- Creates index if not available
- Requires `zac` Solr core to be available (fails to start otherwise)

## Indexing Flow

1. ZAC receives notification from Open Notificaties (e.g., new zaak created)
   OR ZAC internally detects stale data (e.g., task created)
2. ZAC indexes relevant data in Solr

## Reindexing

- Feature to rebuild Solr index per data type (zaken, taken, documents)
- Deletes all data for selected type, then reindexes from source
- Can take considerable time depending on data volume
- Triggered via script (`scripts/solr/reindex-zac-solr-data.sh`) or internal API
- Protected with `X-API-KEY` header

## Solr Version

Current: Solr 9.10.1
