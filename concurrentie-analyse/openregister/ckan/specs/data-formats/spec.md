---
status: draft
source: competitive-analysis
competitor: ckan
analyzed_date: 2026-03-14
---

# Data Formats and Metadata Standards

## What It Does

CKAN supports a wide range of data formats for resources and implements metadata standards (DCAT, Schema.org) for government data interoperability. Resources can be any file type (CSV, JSON, XML, PDF, GeoJSON, Shapefile) or external URLs, with format-aware preview and processing.

## How It Works

**Resource formats:**
The `resource` model stores format as a text field. Common formats with built-in support:
- **Tabular:** CSV, TSV, XLS, XLSX (auto-pushed to DataStore for querying)
- **Structured:** JSON, XML, RDF
- **Geographic:** GeoJSON, Shapefile, KML, WMS, WFS
- **Documents:** PDF, HTML, DOC
- **APIs:** REST endpoints, SPARQL endpoints

**DataPusher/xloader integration:**
When a tabular resource (CSV/Excel) is uploaded, DataPusher or xloader automatically parses it into a DataStore table, making individual rows queryable via the DataStore API.

**DCAT metadata (via ckanext-dcat):**
CKAN maps its package metadata to DCAT (Data Catalog Vocabulary):
- Package -> `dcat:Dataset`
- Resource -> `dcat:Distribution`
- Organization -> `dcat:Organization`
- Tags -> `dcat:keyword`

Exports in RDF/XML, Turtle, JSON-LD formats. Enables interoperability with European data portals and government metadata standards.

**Schema.org markup:**
Dataset pages include Schema.org JSON-LD for search engine indexing:
```json
{
    "@type": "Dataset",
    "name": "...",
    "description": "...",
    "distribution": [{"@type": "DataDownload", "contentUrl": "..."}]
}
```

**Linked Data:**
Extensions provide SPARQL endpoints and RDF export of CKAN metadata, supporting linked data initiatives.

## Key Source Files
- `ckan/model/resource.py` - Resource model with format, mimetype, size
- `ckanext/datapusher/` - Auto-push CSV/Excel to DataStore
- `ckanext-dcat` (separate repo) - DCAT/RDF export
- `ckan/lib/datapreview.py` - Format-aware preview selection

## Relevance to OpenRegister

OpenRegister stores structured objects validated against JSON Schema, which is a different paradigm from CKAN's file-based resources. However, CKAN's DCAT metadata mapping is valuable for government data portals. OpenRegister could benefit from DCAT export capabilities for interoperability with European open data infrastructure, especially for Dutch government compliance.
