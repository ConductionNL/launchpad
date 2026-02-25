# Proposal: plot-applications-on-views

## Summary
Enable organizations to overlay their applications (modules) onto GEMMA ArchiMate views by plotting them inside the referentiecomponenten they implement. Applications are linked to views through gebruiksobjecten (usage objects), which connect modules to referentiecomponenten. This gives organizations a visual map of their IT landscape within the GEMMA architecture.

## Motivation
The GEMMA views currently show the standard architecture (referentiecomponenten, bedrijfsfuncties, etc.) but not which actual applications an organization uses. Organizations need to see their own software plotted onto these views to understand how their IT landscape maps to the GEMMA standard. This is a core value proposition of the Softwarecatalogus: making the abstract GEMMA architecture concrete and actionable per organization.

## Affected Projects
- [x] Project: `softwarecatalog` — Backend enrichment of view data with applications/gebruik, deelnemers support on gebruiksobjecten
- [x] Project: `tilburg-woo-ui` — Frontend rendering of application overlays on views (both beheer and public views)

## Scope
### In Scope
- Fetching applications for the active organization (direct ownership + via gebruiksobjecten)
- Fetching deelnames-gebruik (applications shared via deelnemers on gebruiksobjecten)
- Plotting applications as child nodes inside referentiecomponenten on the view
- Ensuring deelnemers field works on gebruiksobjecten (may need test data)
- Finding and setting the organization with the most applications as active for performance testing
- Performance optimization using `paper.freeze()`/`paper.unfreeze()` pattern already established
- Filter toggles (gebruik, product, deelnames) to show/hide overlay layers

### Out of Scope
- Editing applications or gebruiksobjecten from within the view
- Creating new gebruiksobjecten from the view
- Koppelingen (connections between applications) rendering on views
- Drag-and-drop repositioning of plotted applications
- Real-time collaboration or live updates

## Approach
1. **Investigate existing enrichment** — ViewService already has `expandModulesToViewNodes()` and enrichment parameters (`include_modules`, `include_gebruik`, `include_deelnames_gebruik`). Understand what works and what's missing.
2. **Ensure deelnemers data** — Verify gebruiksobjecten have deelnemers populated; create test data if needed.
3. **Backend enrichment** — Use existing ViewService enrichment to return modules nested under their referentiecomponenten as additional viewNodes.
4. **Frontend rendering** — Render enriched module nodes within referentiecomponenten, styled distinctly from standard GEMMA elements. Apply freeze/unfreeze optimization for potentially large node counts.
5. **Performance test** — Set the organization with the most applications/gebruiksobjecten as active and verify the BBN poster view (388+ nodes) still renders under 3 seconds with overlays.

## Cross-Project Dependencies
- **OpenRegister**: ObjectService for querying applications and gebruiksobjecten (already integrated)
- **@conduction/archimate-diagram-engine**: ViewRenderer.renderToGraph must handle the additional overlay nodes (investigate if child node insertion requires special handling)

## Rollback Strategy
- All overlay rendering is controlled by filter toggles (`gebruik`, `product`, `deelnames` checkboxes)
- Disabling these filters returns the view to its current state (base GEMMA architecture only)
- Backend enrichment parameters are opt-in — omitting them returns the base view
- No schema migrations or destructive changes involved

## Open Questions
1. Does `ViewRenderer.renderToGraph()` support dynamically adding child nodes to existing parent nodes, or do we need to inject them into the viewNodes array before the initial render call?
2. How should overlaid applications be visually distinguished from standard referentiecomponenten? (Color coding, badges, opacity?)
3. When an application maps to multiple referentiecomponenten, should it appear as a copy in each one, or should there be a visual link between instances?
4. What's the expected scale? Organizations with 1000+ applications could add thousands of overlay nodes — what's the performance ceiling?
