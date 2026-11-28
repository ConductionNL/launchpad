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

- [x] PDOK tile sources in the shared map widget: `CnMapWidgetForm` offers OpenStreetMap and the PDOK BRT achtergrondkaart (standaard, grijs, pastel) and luchtfoto presets, in EPSG:3857, stored as plain `basemaps[]` items (nextcloud-vue PR [ConductionNL/nextcloud-vue#2219](https://github.com/ConductionNL/nextcloud-vue/pull/2219), merged into main 19 Nov, released in 2.182.0).
- [x] Geo-property data source in the shared map widget: `markers.geoProperty` draws each object from that property, a GeoJSON geometry of any type (point, line, polygon, multi-geometry) or a lat/lng value, falling back to `@self.geo` (same PR).
- [x] Raise launchpad's `@conduction/nextcloud-vue` pin to the release that carries that PR (2.182.0).
- [x] Spatial filters (bbox / polygon / nearest-N) in the shared widget's data source, through OpenRegister's geo filters. Not in the PR above.
  - Built in nextcloud-vue (#2220, released in 2.183.0, change `map-spatial-filter-and-detail-link`): `markers.dataSource.spatialFilter` takes `{ bbox }`, `{ near, radius }` or `{ within: <Polygon> }` and sends them as OpenRegister's `geo.bbox`, `geo.near`/`geo.radius` or a `geo-search` POST, with `geoProperty` as `geo.property`; a changed filter reads the markers again. OpenRegister has no nearest-N query, so a radius with `limit` stands in for it. launchpad pins nextcloud-vue 2.184.0.
- [x] Click-to-detail: clicking a feature opens the underlying object's deep-link in a new tab using the deep-link-registry.
  - Built in the same nextcloud-vue PR: `markers.detailLink` (tokens `{id}`, `{register}`, `{schema}`, `{<property>}`) opens a clicked object in a new tab; without it, the `url` the object carries is opened, which is the address OpenRegister's deep-link registry stamps where it stamps one. OpenRegister's object list does not stamp that `url` on every row yet, so a dashboard map names its `detailLink`.

## Backend

- [x] Document the `map` widget type in the canonical launchpad widget catalogue.
  - `docs/features/widgets.md` § map: `geoProperty`, `spatialFilter`, `detailLink`, the PDOK presets, an example and the save-time checks.
- [x] Validate `map`-typed widget configurations on save (left: the library widget's content has no `geoProperty`, and a map of `layers` only, or of a `url` source, is valid without a register or schema, so the rule as written would refuse working maps; it needs restating against the library's content shape first): required `register`, `schema`, `geoProperty` keys; optional `spatialFilter`, `clusterThreshold`.
  - Restated against the library's content shape and built as `lib/Service/MapContentValidator.php`, called from `WidgetService::validateWidgetContent()` for `map`: a register source needs both register and schema, a spatial filter is exactly one readable bbox, radius or polygon and needs a register source, a detail link is an app path or an http(s) address, `geoProperty` a name. A map of `layers` only, or of inline or `url` markers, stays valid without a register. `clusterThreshold` is not a library key, so it is not checked.

## Testing

- [x] Unit test for `MapWidgetConfig.vue` validation logic.
  - The map's form is the library's `CnMapWidgetForm`, so there is no `MapWidgetConfig.vue`; the save-time rules are unit-tested in `tests/Unit/Service/MapContentValidatorTest.php` (18 cases).
- [x] Unit test for the spatial-filter to API-query translator in the store.
  - The translator is nextcloud-vue's `spatialFilterRequest()`, tested in its `tests/components/CnMapWidgetSpatialFilter.spec.js` (bbox, radius, polygon, unreadable filters, and the requests the widget makes).
- [x] Playwright smoke: drop a `map` widget on a fresh dashboard, configure it against a register/schema with geo data, confirm features render and click-to-detail navigates correctly.
  - 2026-11-22: tests/e2e/map-widget-smoke.spec.ts passed on the live stack (triage/lpf-6-bundle.log): three markers, tooltip, and the detail link opens in a new tab at the dashboard's slug address.

## Documentation

- [x] Add a "Map widget" section to the dashboard-authoring docs. (`docs/features/widgets.md`, `## map`, and a catalogue row.)
- [x] Cross-reference OpenRegister's geo-metadata-kaart change so authors know which OR features ship the underlying data. (In the `map` section.)

## Out of scope (separate follow-up changes)

- BAG / BGT base-registration overlays — `map-overlays-bag-bgt`.
- INSPIRE metadata compliance — publication-side concern, not widget-side.
- Map drawing / geometry editing — `map-drawing-tools`.
