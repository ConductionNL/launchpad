---
status: draft
source: competitive-analysis
competitor: nocobase
analyzed_date: 2026-03-14
---

# Map Fields

## Purpose

NocoBase's `plugin-map` adds geospatial field types and a map block for visualizing location data. It supports point, polygon, line, and circle geometries.

## Architecture Overview

The map plugin registers custom field types that store GeoJSON-compatible data and provides a map block component for visualization.

```
Map Block (Leaflet/AMap/Google Maps)
    |
    v
Collection with Geo Fields
    |
    v
Geo Field Types (point, polygon, lineString, circle)
    |
    v
Database (JSON storage)
```

## Data Model

### Custom Field Types

1. **Point** - `[longitude, latitude]` coordinate pair
2. **Polygon** - Array of coordinate pairs forming a closed shape
3. **LineString** - Array of coordinate pairs forming a line
4. **Circle** - Center point + radius

### Interfaces
Each field type has a corresponding interface:
- `PointInterface` - Renders as map picker (click to set location)
- `PolygonInterface` - Renders as map polygon drawer
- `LineStringInterface` - Renders as map line drawer
- `CircleInterface` - Renders as map circle drawer

### Value Parsers
For import/export, each type has a value parser that handles serialization:
- `PointValueParser` - Parses "lat,lng" strings
- `PolygonValueParser` - Parses coordinate arrays
- `LineStringValueParser` - Parses coordinate arrays
- `CircleValueParser` - Parses center + radius

## Business Logic

### Map Configuration
`/api/map-configuration:get` and `:set` endpoints for configuring:
- Map provider (AMap/Google Maps/custom)
- API keys for map providers
- Default zoom level and center

### Map Block
A map block in the UI builder:
- Displays collection records as markers/shapes on a map
- Click markers to view record details
- Filter integration with filter blocks
- Clustering for dense point data

### Geo Field in Forms
When editing a record with a geo field:
- Interactive map picker appears
- Click/draw on map to set value
- Manual coordinate input available

## Requirements

### Functional
- Four geometry types (point, polygon, line, circle)
- Map visualization block
- Interactive map picker in forms
- Map provider configuration
- Import/export geo data

### Non-functional
- Map provider abstraction (swap providers)
- Efficient rendering of many markers
- Responsive map sizing

## Comparison Notes

### vs OpenRegister
- OpenRegister has no built-in geospatial support
- NocoBase stores geo data as JSON; proper geo would use PostGIS
- NocoBase has client-side map rendering; OpenRegister would need a map component
- The geo field types could inspire location-aware register objects
- Dutch government use cases often need geospatial data (BAG addresses, CBS areas)
