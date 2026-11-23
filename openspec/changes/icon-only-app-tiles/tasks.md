# Tasks: icon-only-app-tiles

- [ ] `TileWidget.vue`: icon-only render (square, white icon at about 45%, no label), the title as accessible name and tooltip, the marks `aria-hidden` and their meaning in the accessible name
- [ ] Count resolver (`src/services/tileCount.js`): `register` sources through OpenRegister's count with `@me` replaced by the viewer, `nc-widget` sources through the dashboard API v2 items; at most once a minute; no pill on 0, error or missing app
- [ ] Outage dot from the tile's health ping result (REQ-HPING-003 cache) in place of the count; drop the static `content.status` and `content.badge` reads
- [ ] Tile form (`src/modals/TileEditor.vue`): colour as a radio group of the eight theme tokens with a white icon preview; store `colourToken`; hex colours keep rendering
- [ ] Demo seed: the app tiles name their count source and colour token instead of static values
- [ ] Translations for the accessible names and swatch names in every shipped locale
- [ ] Unit tests: icon-only render and accessible name; count resolver for both source types, 0, 99+, missing app; outage replaces the count; colour token stored and rendered, unknown token falls back
- [ ] Playwright: a dashboard with app tiles shows a live count and the outage dot; choosing a swatch in the tile form changes the tile colour
