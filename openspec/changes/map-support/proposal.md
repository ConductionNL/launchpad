# Map support — render geospatial dashboard widgets

Render geospatial properties from OpenRegister objects on a map widget inside launchpad dashboards. Adds a new `map` widget type alongside the existing chart / KPI / table widgets, consuming the GeoJSON storage + spatial-query API that OpenRegister's `geo-metadata-kaart` change is shipping.

## Affected code units

- `@conduction/nextcloud-vue` `CnMapWidget` / `CnMapWidgetForm` (in the nextcloud-vue repository): PDOK base map presets and the `markers.geoProperty` data source (point, line, polygon, multi-geometry).
- LaunchPad: only the `@conduction/nextcloud-vue` pin, once that change is released. The communal `map` type already reaches the widget picker through `widgetRegistry.js`.
- No DB schema change.

## Why a new change

Map rendering is a contained UI surface that depends on geospatial data shipped by OpenRegister, but the rendering decisions (Leaflet vs MapLibre, tile-layer choice, layer control UX, clustering strategy) are launchpad-specific. Splitting this change keeps the OpenRegister geo spec focused on storage + API contracts and lets launchpad iterate the widget independently.

## Approach

- The map is nextcloud-vue's shared `CnMapWidget` (Leaflet 1.9 with clustering), registered communally as the `map` widget type. LaunchPad builds no map widget and adds no map library; PDOK tile sources and the geo-property data source are added to the shared widget, keeping the content shape existing map widgets store (Ruben, 19 Nov 2026).
- Default base layer: PDOK BRT achtergrondkaart (`https://service.pdok.nl/brt/achtergrondkaart/wmts/v2_0`).
- Coordinate transformation (WGS84 ↔ EPSG:28992 RD) via `proj4` when widgets request RD-projected output.
- Widget configuration UI: register/schema picker (reuse existing dashboard-widget config), spatial filter (none / bbox / drawn polygon), clustering threshold.
- Click-to-detail: clicking a feature opens the underlying OpenRegister object's deep-link in a new tab via the existing deep-link-registry.

## Hand-off context

Filed as a sibling to:
- OpenRegister `geo-metadata-kaart` change (decision 2026-05-02): scope-tightened to GeoJSON storage + spatial-query API; UI deferred to consuming apps. https://github.com/ConductionNL/openregister/blob/feat/openspec-batch-impl-2026-05-02/openspec/changes/geo-metadata-kaart/tasks.md
- WOO Tilburg sibling: https://github.com/ConductionNL/tilburg-woo-ui/issues/436

## Notes

- Out of scope for this change: BAG/BGT base-registration overlays (deferred to a follow-up `map-overlays-bag-bgt` change once the base widget is in production).
- Out of scope: INSPIRE metadata compliance (a publication-side concern, not a widget-side concern).
- Out of scope: WFS/GeoJSON export from the widget — the OpenRegister API already exposes GeoJSON; widget consumers can call the API directly.
