# Tasks: Map support widget

## Frontend

> Built in the shared library, not in LaunchPad (Ruben, 19 Nov 2026).
> `@conduction/nextcloud-vue` ships `CnMapWidget` and `CnMapWidgetForm` and
> registers them communally as the `map` dashboard widget type; LaunchPad's
> `widgetRegistry.js` offers every communal type with a form, so `map` is in the
> widget picker without a LaunchPad widget. LaunchPad adds no map library of its
> own (no `@vue-leaflet/vue-leaflet`, no `vue2-leaflet`). The library is extended
> instead, keeping the stored content shape of existing map widgets: new keys
> are optional, and a chosen base map is stored as an ordinary `basemaps[]` item.

- [ ] PDOK tile sources in the shared map widget: `CnMapWidgetForm` offers OpenStreetMap and the PDOK BRT achtergrondkaart (standaard, grijs, pastel) and luchtfoto presets, in EPSG:3857, stored as plain `basemaps[]` items (nextcloud-vue PR [ConductionNL/nextcloud-vue#2219](https://github.com/ConductionNL/nextcloud-vue/pull/2219), against main, not yet merged).
- [ ] Geo-property data source in the shared map widget: `markers.geoProperty` draws each object from that property, a GeoJSON geometry of any type (point, line, polygon, multi-geometry) or a lat/lng value, falling back to `@self.geo` (same PR).
- [ ] Raise launchpad's `@conduction/nextcloud-vue` pin to the release that carries that PR.
- [ ] Spatial filters (bbox / polygon / nearest-N) in the shared widget's data source, through OpenRegister's geo filters. Not in the PR above.
- [ ] Click-to-detail: clicking a feature opens the underlying object's deep-link in a new tab using the deep-link-registry.

## Backend

- [ ] Document the `map` widget type in the canonical launchpad widget catalogue.
- [ ] Validate `map`-typed widget configurations on save (left: the library widget's content has no `geoProperty`, and a map of `layers` only, or of a `url` source, is valid without a register or schema, so the rule as written would refuse working maps; it needs restating against the library's content shape first): required `register`, `schema`, `geoProperty` keys; optional `spatialFilter`, `clusterThreshold`.

## Testing

- [ ] Unit test for `MapWidgetConfig.vue` validation logic.
- [ ] Unit test for the spatial-filter to API-query translator in the store.
- [ ] Playwright smoke: drop a `map` widget on a fresh dashboard, configure it against a register/schema with geo data, confirm features render and click-to-detail navigates correctly.

## Documentation

- [x] Add a "Map widget" section to the dashboard-authoring docs. (`docs/features/widgets.md`, `## map`, and a catalogue row.)
- [x] Cross-reference OpenRegister's geo-metadata-kaart change so authors know which OR features ship the underlying data. (In the `map` section.)

## Out of scope (separate follow-up changes)

- BAG / BGT base-registration overlays — `map-overlays-bag-bgt`.
- INSPIRE metadata compliance — publication-side concern, not widget-side.
- Map drawing / geometry editing — `map-drawing-tools`.
