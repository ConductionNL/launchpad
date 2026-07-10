# Tasks — Beta surface alignment (LaunchPad)

- [x] 1. Fix `appinfo/info.xml`: `<licence>` tag EUPL-1.2, top-of-file SPDX
      header EUPL-1.2/Conduction B.V., remove the `<app>openregister</app>`
      hard dependency (contradicts the app's own OR-free architecture
      policy), add EN+NL description bullets for role-based widget access,
      dashboard sharing, and group dashboards.
- [x] 2. Rewrite `conduction-website/src/pages/apps/launchpad.mdx` (EN
      product page): remove fabricated chart-types/WebSocket/direct-OR/
      OpenCatalogi/OpenConnector claims; rewrite hero, feature list,
      rotating cards, widget shelf, showcase, and "pairs well with" section
      around the verified canonical feature list; correct the displayed
      version from `v0.9` to `v1.0.5`.
- [x] 3. Author the Dutch product page at the correct path
      `conduction-website/i18n/nl/docusaurus-plugin-content-pages/apps/launchpad.mdx`
      (translating the corrected EN content) and remove the stale duplicate
      at the old pre-rename path `apps/mydash.mdx`.
- [x] 4. Fix `docs/intro.md`: replace the fabricated "KPI widgets and live
      charts on top of your OpenRegister data" description and feature list
      with the verified canonical feature list.
- [x] 5. Fix brand-name-only "MyDash" → "LaunchPad" prose references in
      `docs/architecture.md`, `docs/widgets/or-data.md`,
      `docs/tutorials/user/11-sharing-dashboards-publicly.md`, and
      `docs/migration/widget-library-to-ncvue.md`, leaving lowercase
      technical `mydash` identifiers (routes, table names, i18n domain,
      localStorage keys) untouched since those remain factually accurate.
- [x] 6. Verify `img/app.svg` icon convention (white fill, 24×24) against
      the product page's brand tile — confirmed match, no action needed.
- [x] 7. Document the unresolved app-id/namespace inconsistency
      (`mydash` vs `launchpad`) and the `1.0.x` vs fleet-standard `0.x.y`
      beta version format as decisions for a human, not silently fixed.
- [x] 8. Write this `openspec/changes/beta-surface-alignment/` change
      (proposal, tasks, spec delta) documenting the above.
