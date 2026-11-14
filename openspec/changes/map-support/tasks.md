# Tasks: Map support widget

## Frontend

> Superseded by the shared library. `@conduction/nextcloud-vue` (^2.180,
> launchpad's pin) ships `CnMapWidget` and `CnMapWidgetForm` and registers them
> communally as the `map` dashboard widget type (Leaflet with clustering, an
> OpenStreetMap basemap, `markers.dataSource` as register + schema or url,
> autoFit). LaunchPad's `widgetRegistry.js` offers every communal type with a
> form, so `map` is in the widget picker without a LaunchPad change, and the
> build rules say pages and widgets come from the shared widgets rather than a
> hand-built one. The items below are left unticked: they describe a
> `vue2-leaflet` widget for a Vue 2 code base that no longer exists
> (launchpad is on Vue 3, and `widgetTypes.js` / `store/modules/widgetData.js`
> are gone). PDOK tiles, an RD (EPSG:28992) projection and polygon spatial
> filters are not in the library widget; they belong in nextcloud-vue.

- [ ] Add `vue2-leaflet` + `leaflet` + `proj4` to `package.json`; register `<l-map>` / `<l-tile-layer>` / `<l-geo-json>` globals in the dashboard view bootstrap.
- [ ] Create `src/components/widgets/MapWidget.vue` with: tile layer (PDOK BRT default), GeoJSON renderer, click-to-detail handler, basic legend, loading / empty / error states.
- [ ] Create `src/components/widgets/MapWidgetConfig.vue` for the dashboard-author UI: register/schema picker, geo-property selector, spatial-filter mode (none / bbox / polygon), clustering threshold.
- [ ] Register `map` in `src/constants/widgetTypes.js` with a label, icon (MDI `Map`), and config-component reference.
- [ ] Wire `widgetData.js` (or equivalent store module) to call OpenRegister's spatial-query API when the widget type is `map`. Pass through bbox / polygon / nearest-N filters from the widget config.
- [ ] Click-to-detail: clicking a feature opens the underlying object's deep-link in a new tab using the deep-link-registry.

## Backend

- [ ] Document the `map` widget type in the canonical launchpad widget catalogue.
- [x] Validate `map`-typed widget configurations on save, restated against the library widget's content shape (`lib/Service/MapWidgetValidator.php`, wired into `WidgetService::validateWidgetContent()` for `map` and its `map-viewer` alias on create and update). Nothing is required, since a map of `layers` only or of a `url` source is valid without a register or schema; what is present must be well-formed: `register` and `schema` come together; `markers` is an object; `markers.dataSource` names either a `url` or a `register` + `schema`, exactly one; `layers` is a list of objects of type `tile`, `wms`, `wfs`, `geojson` or `image`; `center` is `[lat, lng]`. The original `geoProperty`, `spatialFilter` and `clusterThreshold` keys do not exist in the library widget.

## Testing

> The two unit-test items below name `MapWidgetConfig.vue` and a store-side
> spatial-filter translator, which are superseded with the frontend items above
> (the form and the data fetch live in nextcloud-vue). The save validation is
> covered by `tests/Unit/Service/MapWidgetValidatorTest.php` (25 tests). The
> Playwright smoke needs a live instance and is left for that lane.

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
