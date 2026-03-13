---
status: draft
source: competitive-analysis-docs
competitor: objects-api
analyzed_date: 2026-03-12
---

# Geographic Search — Objects API (Documentation View)

## Purpose
Store and query objects with geographic coordinates using GeoJSON. Supports spatial queries for finding objects within geographic areas.

## Official Documentation
- https://objects-and-objecttypes-api.readthedocs.io/en/latest/introduction/features.html
- OpenAPI spec geometry schemas

## API Reference
| Method | Path | Description |
|--------|------|-------------|
| POST | `/objects/search` | Geo search with geometry.within filter |

### Geo Search Request
```json
{
  "geometry": {
    "within": {
      "type": "Polygon",
      "coordinates": [[[4.0, 52.0], [5.0, 52.0], [5.0, 53.0], [4.0, 53.0], [4.0, 52.0]]]
    }
  }
}
```

## Supported Geometry Types
- Point
- MultiPoint
- LineString
- MultiLineString
- Polygon
- MultiPolygon
- GeometryCollection

## CRS Support
Only EPSG:4326 (WGS84) is supported, following the GeoJSON specification.

Required headers:
- `Content-Crs: EPSG:4326` — CRS of request data
- `Accept-Crs: EPSG:4326` — Desired CRS of response

## Configuration
- Objecttype `allowGeometry` flag controls whether objects can have coordinates
- Requires PostgreSQL with PostGIS extension

## OpenRegister Comparison
| Aspect | Objects API | OpenRegister |
|--------|-----------|--------------|
| Geometry storage | GeoJSON in record.geometry | No native geometry |
| Spatial queries | within (point-in-polygon) | No |
| CRS | EPSG:4326 only | N/A |
| Geometry types | All GeoJSON types | N/A |
| Database requirement | PostGIS | Standard PostgreSQL |
| Per-type geometry toggle | allowGeometry flag | N/A |

**Already in OpenRegister**: None
**Not yet in OpenRegister**: GeoJSON geometry storage, spatial search (within), PostGIS integration, CRS headers, per-type geometry configuration
