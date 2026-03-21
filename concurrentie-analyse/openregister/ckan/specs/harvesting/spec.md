---
status: draft
source: competitive-analysis
competitor: ckan
analyzed_date: 2026-03-14
---

# Data Harvesting

## What It Does

CKAN's harvesting framework (ckanext-harvest) enables automatic collection of datasets from remote sources. It supports harvesting from other CKAN instances, CSW/WMS geospatial services, DCAT feeds, and custom sources -- creating a federated data ecosystem.

## How It Works

The harvesting system follows a three-stage pipeline:

1. **Gather** - Connect to the remote source and collect a list of dataset identifiers
2. **Fetch** - Download the full metadata for each identified dataset
3. **Import** - Create or update CKAN packages from the fetched metadata

Harvest sources are configured through the CKAN admin UI or API:
```json
{
    "url": "https://data.gov/api/3",
    "source_type": "ckan",
    "frequency": "DAILY",
    "config": {
        "default_groups": ["government"],
        "default_extras": {"harvest_source": "data.gov"}
    }
}
```

Built-in harvester types:
- **CKAN Harvester** - Harvests from other CKAN instances via Action API
- **CSW Harvester** - OGC Catalogue Service for the Web (geospatial metadata)
- **WAF Harvester** - Web Accessible Folder of XML metadata documents
- **Doc Harvester** - Single XML document harvester

The framework uses Redis Queue (RQ) for background job processing. Harvest jobs track status per dataset: `new`, `changed`, `unchanged`, `error`.

Custom harvesters implement the `IHarvester` interface:
```python
class MyHarvester(HarvesterBase):
    def gather_stage(self, harvest_job):
        # Return list of HarvestObject IDs
    def fetch_stage(self, harvest_object):
        # Download and store content
    def import_stage(self, harvest_object):
        # Create/update CKAN package
```

## Key Source Files
- `ckanext-harvest` (separate repository) - Core harvesting framework
- `ckanext/harvest/harvesters/ckanharvester.py` - CKAN-to-CKAN harvester
- `ckanext/harvest/model.py` - HarvestSource, HarvestJob, HarvestObject models
- `ckanext/harvest/queue.py` - Redis-based job queue

## Relevance to OpenRegister

OpenRegister has data source syncing capabilities but not a formal harvesting framework. CKAN's three-stage pipeline (gather/fetch/import) with per-record status tracking is a mature pattern for federated data collection. The ability to harvest from multiple source types via plugins is something OpenRegister could benefit from for government data integration scenarios.
